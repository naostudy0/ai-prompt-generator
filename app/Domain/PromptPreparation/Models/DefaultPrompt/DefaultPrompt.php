<?php

namespace App\Domain\PromptPreparation\Models\DefaultPrompt;

final readonly class DefaultPrompt
{
    public function __construct(
        public PromptPolarity $polarity,
        public DefaultPromptText $text,
    ) {
    }
}
