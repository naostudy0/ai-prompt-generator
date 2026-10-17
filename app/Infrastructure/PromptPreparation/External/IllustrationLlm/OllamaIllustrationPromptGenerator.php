<?php

namespace App\Infrastructure\PromptPreparation\External\IllustrationLlm;

use App\Application\PromptPreparation\DTO\GeneratedIllustrationPrompt;
use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Ports\IllustrationPromptGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class OllamaIllustrationPromptGenerator implements IllustrationPromptGenerator
{
    public function generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt
    {
        $requestId = (string) Str::uuid();
        $configuredModel = (string) config('services.illustration_llm.model');
        $startedAt = hrtime(true);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout((int) config('services.illustration_llm.connect_timeout'))
                ->timeout((int) config('services.illustration_llm.timeout'))
                ->withoutRedirecting()
                ->post($this->endpoint(), [
                    'model' => $configuredModel,
                    'stream' => false,
                    'think' => false,
                    'options' => [
                        'num_predict' => (int) config('services.illustration_llm.num_predict'),
                    ],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $description],
                    ],
                    'format' => [
                        'type' => 'object',
                        'properties' => [
                            'positive_prompt' => ['type' => 'string'],
                            'negative_prompt' => ['type' => 'string'],
                        ],
                        'required' => ['positive_prompt', 'negative_prompt'],
                        'additionalProperties' => false,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->logFailure($requestId, $configuredModel, $startedAt, 'connection_error');

            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。', 0, $exception);
        } catch (Throwable $exception) {
            $this->logFailure($requestId, $configuredModel, $startedAt, 'request_error');

            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。', 0, $exception);
        }

        if (!$response->successful()) {
            $this->logFailure($requestId, $configuredModel, $startedAt, 'http_error', $response->status());

            throw new IllustrationPromptServiceUnavailable('外部LLMを利用できません。');
        }

        $data = $response->json();
        if (!is_array($data) || array_is_list($data)) {
            $this->invalidResponse($requestId, $configuredModel, $startedAt, 'invalid_outer_json');
        }
        $content = $data['message']['content'] ?? null;
        if (!is_string($content)) {
            $this->invalidResponse($requestId, $configuredModel, $startedAt, 'missing_content');
        }

        try {
            $generated = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalidResponse($requestId, $configuredModel, $startedAt, 'invalid_content_json');
        }
        if (!is_array($generated) || array_is_list($generated)) {
            $this->invalidResponse($requestId, $configuredModel, $startedAt, 'invalid_content_object');
        }
        $positive = $generated['positive_prompt'] ?? null;
        $negative = $generated['negative_prompt'] ?? null;
        if (!is_string($positive) || !is_string($negative) || trim($positive) === '') {
            $this->invalidResponse($requestId, $configuredModel, $startedAt, 'invalid_prompt_fields');
        }

        $model = is_string($data['model'] ?? null) && trim($data['model']) !== ''
            ? trim($data['model'])
            : $configuredModel;
        $elapsedMs = $this->elapsedMilliseconds($startedAt);
        Log::info('Illustration prompt generation completed.', array_merge([
            'request_id' => $requestId,
            'outcome' => 'success',
            'model' => $model,
            'elapsed_ms' => $elapsedMs,
        ], $this->metrics($data)));

        return new GeneratedIllustrationPrompt(
            positivePrompt: trim($positive),
            negativePrompt: trim($negative),
            model: $model,
            generationTimeMs: $elapsedMs,
        );
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.illustration_llm.base_url'), '/').'/api/chat';
    }

    /** @param array<string, mixed> $data
     *  @return array<string, int|float>
     */
    private function metrics(array $data): array
    {
        $metrics = [];
        foreach (['total_duration', 'load_duration', 'prompt_eval_duration', 'eval_duration'] as $field) {
            if (is_int($data[$field] ?? null) || is_float($data[$field] ?? null)) {
                $metrics[str_replace('_duration', '_duration_ms', $field)] = $data[$field] / 1_000_000;
            }
        }
        foreach (['prompt_eval_count', 'eval_count'] as $field) {
            if (is_int($data[$field] ?? null) || is_float($data[$field] ?? null)) {
                $metrics[$field] = $data[$field];
            }
        }
        $evalDuration = $data['eval_duration'] ?? null;
        $evalCount = $data['eval_count'] ?? null;
        if ((is_int($evalDuration) || is_float($evalDuration)) && $evalDuration > 0
            && (is_int($evalCount) || is_float($evalCount))) {
            $metrics['tokens_per_second'] = $evalCount / ($evalDuration / 1_000_000_000);
        }

        return $metrics;
    }

    private function invalidResponse(string $requestId, string $model, int $startedAt, string $reason): never
    {
        $this->logFailure($requestId, $model, $startedAt, $reason);

        throw new InvalidIllustrationPromptResponse('外部LLMの応答形式が不正です。');
    }

    private function logFailure(string $requestId, string $model, int $startedAt, string $outcome, ?int $status = null): void
    {
        $context = [
            'request_id' => $requestId,
            'outcome' => $outcome,
            'model' => $model,
            'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
        ];
        if ($status !== null) {
            $context['status'] = $status;
        }
        Log::warning('Illustration prompt generation failed.', $context);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
