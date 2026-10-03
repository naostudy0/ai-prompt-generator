<?php

namespace App\Application\PromptPreparation\Writes\QueueComfyUiPromptBatch;

use App\Application\PromptPreparation\Exceptions\ComfyUiQueueFailed;
use App\Application\PromptPreparation\Exceptions\ComfyUiWorkflowNotConfigured;
use App\Application\PromptPreparation\Exceptions\InvalidComfyUiWorkflow;
use App\Application\PromptPreparation\Exceptions\InvalidLoraGenerationSelection;
use App\Application\PromptPreparation\Writes\QueueComfyUiPrompt\QueueComfyUiPromptHandler;

final readonly class QueueComfyUiPromptBatchHandler
{
    private const int MAX_SEED = 1125899906842624;

    public function __construct(private QueueComfyUiPromptHandler $queue)
    {
    }

    /**
     * @param list<array{candidateKey: string, modelFamilyId: int, loraId: int, positive: string, negative: string}> $items
     * @return list<array{candidateKey: string, status: 'accepted'|'failed', promptId?: string, queueNumber?: int|float, message?: string, resultUnknown?: bool}>
     */
    public function handle(array $items): array
    {
        $results = [];
        $seeds = [];
        foreach ($items as $item) {
            try {
                do {
                    $seed = random_int(0, self::MAX_SEED);
                } while (isset($seeds[$seed]));
                $seeds[$seed] = true;
                $queued = $this->queue->handle($item['modelFamilyId'], $item['positive'], $item['negative'], $item['loraId'], $seed);
                $results[] = ['candidateKey' => $item['candidateKey'], 'status' => 'accepted'] + $queued;
            } catch (ComfyUiQueueFailed|ComfyUiWorkflowNotConfigured|InvalidComfyUiWorkflow|InvalidLoraGenerationSelection $exception) {
                $results[] = [
                    'candidateKey' => $item['candidateKey'],
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                    'resultUnknown' => $exception instanceof ComfyUiQueueFailed && $exception->resultUnknown,
                ];
            }
        }

        return $results;
    }
}
