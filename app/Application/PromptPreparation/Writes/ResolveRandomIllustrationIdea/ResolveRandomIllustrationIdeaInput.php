<?php

namespace App\Application\PromptPreparation\Writes\ResolveRandomIllustrationIdea;

use App\Application\PromptPreparation\DTO\RandomIllustrationSelectionIds;

final readonly class ResolveRandomIllustrationIdeaInput
{
    public function __construct(public string $ideaId, public string $description, public RandomIllustrationSelectionIds $fixedSelections)
    {
    }
}
