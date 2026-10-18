<?php

namespace App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt;

use App\Application\PromptPreparation\DTO\ReusableIllustrationTagGroup;

final readonly class GenerateIllustrationPromptResult
{
    /** @param list<ReusableIllustrationTagGroup> $reusableTagGroups */
    public function __construct(
        public string $positivePrompt,
        public string $negativePrompt,
        public string $model,
        public string $promptStyle,
        public int $generationTimeMs,
        public array $reusableTagGroups,
    ) {
    }
}
