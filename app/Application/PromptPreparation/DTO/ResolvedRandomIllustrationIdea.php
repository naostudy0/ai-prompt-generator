<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class ResolvedRandomIllustrationIdea
{
    /**
     * @param  list<int>  $selectedOptionIds
     * @param  list<TransientPromptSuggestion>  $suggestions
     * @param  list<string>  $adjustments
     */
    public function __construct(public string $resolvedDescription, public array $selectedOptionIds, public array $suggestions, public array $adjustments, public string $model, public int $generationTimeMs)
    {
    }
}
