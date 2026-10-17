<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class GeneratedIllustrationPrompt
{
    public function __construct(
        public string $positivePrompt,
        public string $negativePrompt,
        public string $model,
        public int $generationTimeMs,
    ) {
    }
}
