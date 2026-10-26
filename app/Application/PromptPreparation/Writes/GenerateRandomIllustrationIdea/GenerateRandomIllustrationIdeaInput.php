<?php

namespace App\Application\PromptPreparation\Writes\GenerateRandomIllustrationIdea;

use App\Application\PromptPreparation\DTO\RandomIllustrationSelectionIds;

final readonly class GenerateRandomIllustrationIdeaInput
{
    public function __construct(public RandomIllustrationSelectionIds $fixedSelections)
    {
    }
}
