<?php

namespace App\Application\PromptPreparation\Writes\SaveDefaultPrompt;

use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;

final readonly class SaveDefaultPromptInput
{
    public function __construct(
        public PromptPolarity $polarity,
        public string $content,
        public int $modelFamilyId = 1,
    ) {
    }
}
