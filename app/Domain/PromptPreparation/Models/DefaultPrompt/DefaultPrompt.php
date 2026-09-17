<?php

namespace App\Domain\PromptPreparation\Models\DefaultPrompt;

use App\Domain\PromptPreparation\Models\PromptText;

final readonly class DefaultPrompt
{
    public function __construct(
        public PromptPolarity $polarity,
        public PromptText $text,
    ) {
    }
}
