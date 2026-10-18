<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class GeneratedIllustrationPrompt
{
    /** @param list<ReusableIllustrationTagGroup> $reusableTagGroups */
    public function __construct(
        public string $positivePrompt,
        public string $negativePrompt,
        public string $model,
        public int $generationTimeMs,
        public array $reusableTagGroups = [],
    ) {
    }
}
