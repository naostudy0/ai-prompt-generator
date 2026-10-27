<?php

namespace App\Infrastructure\PromptPreparation\External\IllustrationLlm;

use App\Application\PromptPreparation\DTO\RandomIllustrationIdea;
use App\Application\PromptPreparation\DTO\RandomIllustrationOptionCandidate;
use App\Application\PromptPreparation\DTO\ResolvedRandomIllustrationIdea;
use App\Application\PromptPreparation\DTO\TransientPromptSuggestion;
use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Ports\RandomIllustrationIdeaGenerator;
use App\Application\PromptPreparation\Ports\RandomIllustrationIdeaResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class OllamaRandomIllustrationService implements RandomIllustrationIdeaGenerator, RandomIllustrationIdeaResolver
{
    public function generate(string $systemPrompt, array $fixed, ?RandomIllustrationOptionCandidate $anchor): RandomIllustrationIdea
    {
        $payload = ['fixed_selections' => $fixed, 'location_anchor' => $anchor];
        [$content, $model, $elapsed] = $this->request($systemPrompt, $payload, [
            'type' => 'object', 'properties' => ['description' => ['type' => 'string']],
            'required' => ['description'], 'additionalProperties' => false,
        ]);
        if (! $this->hasExactKeys($content, ['description']) || ! is_string($content['description']) || trim($content['description']) === '') {
            throw new InvalidIllustrationPromptResponse('外部LLMの応答形式が不正です。');
        }

        return new RandomIllustrationIdea(trim($content['description']), $model, $elapsed);
    }

    public function resolve(string $instructions, string $description, array $fixed, array $candidates): ResolvedRandomIllustrationIdea
    {
        [$content, $model, $elapsed] = $this->request($instructions, ['description' => $description, 'fixed_selections' => $fixed, 'registered_candidates' => $candidates], [
            'type' => 'object',
            'properties' => [
                'resolved_description' => ['type' => 'string'],
                'selected_option_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'transient_suggestions' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'object', 'properties' => ['polarity' => ['type' => 'string', 'enum' => ['positive', 'negative']], 'label' => ['type' => 'string'], 'tags' => ['type' => 'string']], 'required' => ['polarity', 'label', 'tags'], 'additionalProperties' => false]],
                'adjustments' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['resolved_description', 'selected_option_ids', 'transient_suggestions', 'adjustments'], 'additionalProperties' => false,
        ]);
        if (! $this->hasExactKeys($content, ['resolved_description', 'selected_option_ids', 'transient_suggestions', 'adjustments']) || ! is_string($content['resolved_description']) || trim($content['resolved_description']) === '' || ! is_array($content['selected_option_ids']) || ! is_array($content['transient_suggestions']) || ! is_array($content['adjustments'])) {
            throw new InvalidIllustrationPromptResponse('外部LLMの応答形式が不正です。');
        }
        $ids = [];
        foreach ($content['selected_option_ids'] as $id) {
            if (!is_int($id)) {
                throw new InvalidIllustrationPromptResponse('外部LLMの項目IDが不正です。');
            }
            $ids[] = $id;
        }
        $suggestions = [];
        foreach ($content['transient_suggestions'] as $value) {
            if (! is_array($value) || ! $this->hasExactKeys($value, ['polarity', 'label', 'tags']) || ! in_array($value['polarity'] ?? null, ['positive', 'negative'], true) || ! is_string($value['label'] ?? null) || mb_strlen(trim($value['label'])) < 1 || mb_strlen(trim($value['label'])) > 100 || ! is_string($value['tags'] ?? null) || preg_match('/[^,\s]/u', $value['tags']) !== 1) {
                throw new InvalidIllustrationPromptResponse('外部LLMの一時候補が不正です。');
            }
            $suggestions[] = new TransientPromptSuggestion($value['polarity'], trim($value['label']), trim($value['tags']));
        }
        if (!array_is_list($content['adjustments']) || !array_is_list($content['selected_option_ids']) || !array_is_list($content['transient_suggestions']) || !collect($content['adjustments'])->every('is_string')) {
            throw new InvalidIllustrationPromptResponse('外部LLMの調整内容が不正です。');
        }

        return new ResolvedRandomIllustrationIdea(trim($content['resolved_description']), array_values(array_unique($ids)), $suggestions, array_map('trim', $content['adjustments']), $model, $elapsed);
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $schema
     * @return array{array<string, mixed>, string, int}
     */
    private function request(string $systemPrompt, array $userData, array $schema): array
    {
        $requestId = (string) Str::uuid();
        $model = (string) config('services.illustration_llm.model');
        $started = hrtime(true);
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout((int) config('services.illustration_llm.connect_timeout'))->timeout((int) config('services.illustration_llm.timeout'))->withoutRedirecting()->post(rtrim((string) config('services.illustration_llm.base_url'), '/').'/api/chat', [
                'model' => $model, 'stream' => false, 'think' => false,
                'options' => [
                    'num_predict' => (int) config('services.illustration_llm.num_predict'),
                    'temperature' => 0,
                ],
                'messages' => [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => json_encode($userData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]],
                'format' => $schema,
            ]);
        } catch (ConnectionException|JsonException $exception) {
            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。', 0, $exception);
        } catch (Throwable $exception) {
            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。', 0, $exception);
        }
        $elapsed = max(0, (int) round((hrtime(true) - $started) / 1_000_000));
        if (!$response->successful()) {
            Log::warning('Random illustration request failed.', ['request_id' => $requestId, 'model' => $model, 'status' => $response->status(), 'elapsed_ms' => $elapsed]);
            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。');
        }
        $outer = $response->json();
        $raw = is_array($outer) ? ($outer['message']['content'] ?? null) : null;
        if (!is_string($raw)) {
            throw new InvalidIllustrationPromptResponse('外部LLMの応答形式が不正です。');
        }
        try {
            $content = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidIllustrationPromptResponse('外部LLMの応答形式が不正です。', 'invalid_json');
        }
        if (!is_array($content) || array_is_list($content)) {
            throw new InvalidIllustrationPromptResponse('外部LLMの応答形式が不正です。');
        }
        $responseModel = is_string($outer['model'] ?? null) && trim($outer['model']) !== '' ? trim($outer['model']) : $model;
        Log::info('Random illustration request completed.', ['request_id' => $requestId, 'model' => $responseModel, 'elapsed_ms' => $elapsed]);

        return [$content, $responseModel, $elapsed];
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $keys
     */
    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }
}
