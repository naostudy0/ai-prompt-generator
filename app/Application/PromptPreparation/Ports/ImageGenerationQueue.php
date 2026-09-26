<?php

namespace App\Application\PromptPreparation\Ports;

interface ImageGenerationQueue
{
    /** @return array{promptId: string, queueNumber: int|float} */
    public function queue(int $modelFamilyId, string $positive, string $negative, ?int $seed = null): array;
}
