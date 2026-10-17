<?php

namespace App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt;

final readonly class GenerateIllustrationPromptInput
{
    public function __construct(
        public string $description,
        public PromptStyle $promptStyle,
    ) {
    }
}
