<?php

namespace App\Application\PromptPreparation\Queries\ListFavoritePrompts;

use App\Application\PromptPreparation\Queries\FavoritePromptSummary;

final readonly class ListFavoritePromptsResult
{
    /** @param list<FavoritePromptSummary> $favorites */
    public function __construct(public array $favorites)
    {
    }
}
