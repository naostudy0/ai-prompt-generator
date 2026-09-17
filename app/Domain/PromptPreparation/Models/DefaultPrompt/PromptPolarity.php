<?php

namespace App\Domain\PromptPreparation\Models\DefaultPrompt;

enum PromptPolarity: string
{
    case Positive = 'positive';
    case Negative = 'negative';
}
