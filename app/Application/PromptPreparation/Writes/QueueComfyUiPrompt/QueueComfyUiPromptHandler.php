<?php

namespace App\Application\PromptPreparation\Writes\QueueComfyUiPrompt;

use App\Application\PromptPreparation\Ports\ImageGenerationQueue;
use App\Application\PromptPreparation\Ports\LoraGenerationSettingsProvider;

final readonly class QueueComfyUiPromptHandler
{
    public function __construct(
        private ImageGenerationQueue $queue,
        private LoraGenerationSettingsProvider $generationSettings,
    ) {
    }

    /** @return array{promptId: string, queueNumber: int|float} */
    public function handle(int $modelFamilyId, string $positive, string $negative, ?int $loraId, ?int $seed = null): array
    {
        $settings = $loraId === null ? null : $this->generationSettings->getForCharacterLora($loraId, $modelFamilyId);

        return $this->queue->queue($modelFamilyId, $positive, $negative, $seed, $settings);
    }
}
