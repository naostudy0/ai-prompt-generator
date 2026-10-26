<?php

namespace App\Application\PromptPreparation\Writes\ResolveRandomIllustrationIdea;

use App\Application\PromptPreparation\DTO\TransientPromptSuggestion;

final readonly class ResolveRandomIllustrationIdeaResult
{
    /**
     * @param  list<int>  $optionIds
     * @param  list<TransientPromptSuggestion>  $suggestions
     * @param  list<string>  $adjustments
     */
    public function __construct(public string $ideaId, public string $resolvedDescription, public array $optionIds, public array $suggestions, public array $adjustments, public string $model, public int $generationTimeMs)
    {
    }
}
