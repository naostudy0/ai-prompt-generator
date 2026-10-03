<?php

namespace App\Application\PromptPreparation\Ports;

interface ImageGenerationQueue
{
    /**
     * @param array{checkpoint: string, sampler: string, scheduler: string, name: string}|null $generationSettings
     * @return array{promptId: string, queueNumber: int|float}
     */
    public function queue(int $modelFamilyId, string $positive, string $negative, ?int $seed = null, ?array $generationSettings = null): array;
}
