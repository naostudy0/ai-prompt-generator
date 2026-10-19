<?php

namespace App\Infrastructure\PromptPreparation\External\IllustrationLlm;

use App\Application\PromptPreparation\DTO\GeneratedScenarioPrompts;
use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\DTO\PlannedIllustrationScenario;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Ports\IllustrationScenarioPlanner;
use App\Application\PromptPreparation\Ports\ScenarioIllustrationPromptGenerator;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class OllamaIllustrationScenarioGenerator implements IllustrationScenarioPlanner, ScenarioIllustrationPromptGenerator
{
    public function plan(string $systemPrompt, string $description, int $sceneCount): PlannedIllustrationScenario
    {
        $schema = $this->objectSchema(['continuity_notes', 'scenes'], [
            'continuity_notes' => ['type' => 'string'],
            'scenes' => ['type' => 'array', 'minItems' => $sceneCount, 'maxItems' => $sceneCount, 'items' => $this->objectSchema(['id', 'title', 'description', 'changes_from_previous'], [
                'id' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'changes_from_previous' => ['type' => 'string'],
            ])],
        ]);
        [$data, $model, $elapsed] = $this->request('plan', $systemPrompt, ['description' => $description, 'scene_count' => $sceneCount], $schema);
        $notes = $data['continuity_notes'] ?? null;
        $rawScenes = $data['scenes'] ?? null;
        if (!is_string($notes) || !is_array($rawScenes) || !array_is_list($rawScenes) || count($rawScenes) !== $sceneCount) {
            $this->invalid();
        }
        $scenes = [];
        foreach ($rawScenes as $index => $scene) {
            if (!is_array($scene)) {
                $this->invalid();
            }
            $title = $scene['title'] ?? null;
            $descriptionValue = $scene['description'] ?? null;
            $changes = $scene['changes_from_previous'] ?? null;
            if (!is_string($title) || trim($title) === '' || !is_string($descriptionValue) || trim($descriptionValue) === '' || !is_string($changes)) {
                $this->invalid();
            }
            $scenes[] = new IllustrationScenarioScene('scene-'.($index + 1), trim($title), trim($descriptionValue), trim($changes));
        }
        return new PlannedIllustrationScenario(trim($notes), $scenes, $model, $elapsed);
    }

    /** @param list<IllustrationScenarioScene> $scenes */
    public function generateAll(string $systemPrompt, string $description, string $continuityNotes, array $scenes, PromptStyle $promptStyle): GeneratedScenarioPrompts
    {
        $schema = $this->objectSchema(['scenes'], ['scenes' => ['type' => 'array', 'minItems' => count($scenes), 'maxItems' => count($scenes), 'items' => $this->promptSchema()]]);
        [$data, $model, $elapsed] = $this->request('generate_all', $systemPrompt, compact('description', 'continuityNotes', 'scenes', 'promptStyle'), $schema);
        $prompts = $this->parsePrompts($data['scenes'] ?? null, array_map(static fn (IllustrationScenarioScene $scene): string => $scene->id, $scenes));
        return new GeneratedScenarioPrompts($prompts, $model, $elapsed);
    }

    /**
     * @param list<IllustrationScenarioScene> $scenes
     * @param list<ScenarioIllustrationPrompt> $currentPrompts
     */
    public function regenerateOne(string $systemPrompt, string $description, string $continuityNotes, array $scenes, array $currentPrompts, string $targetId, string $instruction, PromptStyle $promptStyle): ScenarioIllustrationPrompt
    {
        [$data] = $this->request('regenerate_one', $systemPrompt, compact('description', 'continuityNotes', 'scenes', 'currentPrompts', 'targetId', 'instruction', 'promptStyle'), $this->promptSchema());
        return $this->parsePrompts([$data], [$targetId])[0];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $schema
     * @return array{array<string, mixed>, string, int}
     */
    private function request(string $operation, string $systemPrompt, array $input, array $schema): array
    {
        $requestId = (string) Str::uuid();
        $started = hrtime(true);
        $model = (string) config('services.illustration_llm.model');
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout((int) config('services.illustration_llm.connect_timeout'))->timeout((int) config('services.illustration_llm.timeout'))->withoutRedirecting()->post(rtrim((string) config('services.illustration_llm.base_url'), '/').'/api/chat', [
                'model' => $model, 'stream' => false, 'think' => false, 'options' => ['num_predict' => (int) config('services.illustration_llm.scenario_num_predict')],
                'messages' => [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => json_encode($input, JSON_THROW_ON_ERROR)]], 'format' => $schema,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Illustration scenario generation failed.', ['request_id' => $requestId, 'operation' => $operation, 'model' => $model, 'outcome' => 'request_error']);
            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。', 0, $exception);
        }
        if (!$response->successful()) {
            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。');
        }
        $content = $response->json('message.content');
        try {
            $decoded = is_string($content) ? json_decode($content, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $this->invalid();
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            $this->invalid();
        }
        $elapsed = max(0, (int) round((hrtime(true) - $started) / 1_000_000));
        Log::info('Illustration scenario generation completed.', ['request_id' => $requestId, 'operation' => $operation, 'model' => $model, 'outcome' => 'success', 'elapsed_ms' => $elapsed]);
        return [$decoded, is_string($response->json('model')) ? $response->json('model') : $model, $elapsed];
    }

    /**
     * @param list<string> $expectedIds
     * @return list<ScenarioIllustrationPrompt>
     */
    private function parsePrompts(mixed $raw, array $expectedIds): array
    {
        if (!is_array($raw) || !array_is_list($raw) || count($raw) !== count($expectedIds)) {
            $this->invalid();
        }
        $result = [];
        foreach ($raw as $index => $item) {
            if (!is_array($item) || ($item['id'] ?? null) !== $expectedIds[$index] || !is_string($item['positive_prompt'] ?? null) || trim($item['positive_prompt']) === '' || !is_string($item['negative_prompt'] ?? null)) {
                $this->invalid();
            }
            $result[] = new ScenarioIllustrationPrompt($expectedIds[$index], trim($item['positive_prompt']), trim($item['negative_prompt']));
        }
        return $result;
    }

    /** @return array<string, mixed> */
    private function promptSchema(): array
    {
        return $this->objectSchema(['id', 'positive_prompt', 'negative_prompt'], ['id' => ['type' => 'string'], 'positive_prompt' => ['type' => 'string'], 'negative_prompt' => ['type' => 'string']]);
    }
    /**
     * @param list<string> $required
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    private function objectSchema(array $required, array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }
    private function invalid(): never
    {
        throw new InvalidIllustrationPromptResponse('外部LLMの応答形式が不正です。');
    }
}
