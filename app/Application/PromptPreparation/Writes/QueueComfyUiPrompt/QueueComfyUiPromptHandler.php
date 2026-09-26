<?php

namespace App\Application\PromptPreparation\Writes\QueueComfyUiPrompt;

use App\Application\PromptPreparation\Ports\ImageGenerationQueue;

final readonly class QueueComfyUiPromptHandler
{
    public function __construct(private ImageGenerationQueue $queue)
    {
    }

    /** @return array{promptId: string, queueNumber: int|float} */
    public function handle(int $modelFamilyId, string $positive, string $negative): array
    {
        return $this->queue->queue($modelFamilyId, $positive, $negative);
    }
}
