<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class MatchedComfyUiWorkflow
{
    public function __construct(
        public int $modelFamilyId,
        public string $positivePrompt,
        public string $negativePrompt,
    ) {
    }
}
