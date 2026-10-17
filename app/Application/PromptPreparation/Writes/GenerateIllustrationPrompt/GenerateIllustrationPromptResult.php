<?php

namespace App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt;

final readonly class GenerateIllustrationPromptResult
{
    public function __construct(
        public string $positivePrompt,
        public string $negativePrompt,
        public string $model,
        public string $promptStyle,
        public int $generationTimeMs,
    ) {
    }
}
