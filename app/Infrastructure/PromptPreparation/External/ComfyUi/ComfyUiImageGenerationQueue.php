<?php

namespace App\Infrastructure\PromptPreparation\External\ComfyUi;

use App\Application\PromptPreparation\Exceptions\ComfyUiQueueFailed;
use App\Application\PromptPreparation\Exceptions\ComfyUiWorkflowNotConfigured;
use App\Application\PromptPreparation\Exceptions\InvalidComfyUiWorkflow;
use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use App\Application\PromptPreparation\Ports\ImageGenerationQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final readonly class ComfyUiImageGenerationQueue implements ImageGenerationQueue
{
    private const int MAX_SEED = 1125899906842624;

    public function __construct(private ComfyUiWorkflowStore $workflows)
    {
    }

    public function queue(int $modelFamilyId, string $positive, string $negative, ?int $seed = null): array
    {
        $stored = $this->workflows->findWorkflow($modelFamilyId);
        if ($stored === null) {
            throw new ComfyUiWorkflowNotConfigured('この系統のComfyUIワークフローが未設定です。');
        }
        $workflow = $stored['workflow'];
        $this->replace($workflow, $stored['mappings']['positive'], $positive, 'positive');
        $this->replace($workflow, $stored['mappings']['negative'], $negative, 'negative');
        $this->replace($workflow, $stored['mappings']['seed'], $seed ?? random_int(0, self::MAX_SEED), 'seed');

        $baseUrl = rtrim((string) config('services.comfyui.base_url'), '/');
        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('services.comfyui.connect_timeout'))
                ->timeout((int) config('services.comfyui.timeout'))
                ->withoutRedirecting()
                ->post("{$baseUrl}/prompt", [
                    'prompt' => $workflow,
                    'client_id' => (string) Str::uuid(),
                ]);
        } catch (ConnectionException $exception) {
            $message = str_contains(strtolower($exception->getMessage()), 'timed out')
                ? 'ComfyUIの応答がタイムアウトしたため、受理結果は不明です。'
                : 'ComfyUIへ接続できませんでした。';
            throw new ComfyUiQueueFailed($message, str_contains($message, '不明'), $exception);
        } catch (Throwable $exception) {
            throw new ComfyUiQueueFailed('ComfyUIへの送信に失敗しました。', false, $exception);
        }
        $data = $response->json();
        if (!$response->successful() || !is_array($data)) {
            throw new ComfyUiQueueFailed('ComfyUIがワークフローを受理しませんでした。');
        }
        $promptId = $data['prompt_id'] ?? null;
        $number = $data['number'] ?? null;
        $nodeErrors = $data['node_errors'] ?? [];
        if (!is_string($promptId) || (!is_int($number) && !is_float($number)) || !is_array($nodeErrors) || $nodeErrors !== []) {
            throw new ComfyUiQueueFailed('ComfyUIの応答またはノード検証結果が不正です。');
        }

        return ['promptId' => $promptId, 'queueNumber' => $number];
    }

    /**
     * @param array<string, mixed> $workflow
     * @param array{nodeId: string, inputName: string} $mapping
     */
    private function replace(array &$workflow, array $mapping, string|int $value, string $role): void
    {
        if (!isset($workflow[$mapping['nodeId']]['inputs']) || !array_key_exists($mapping['inputName'], $workflow[$mapping['nodeId']]['inputs'])) {
            throw new InvalidComfyUiWorkflow("{$role}の入力位置が見つかりません。");
        }
        $workflow[$mapping['nodeId']]['inputs'][$mapping['inputName']] = $value;
    }
}
