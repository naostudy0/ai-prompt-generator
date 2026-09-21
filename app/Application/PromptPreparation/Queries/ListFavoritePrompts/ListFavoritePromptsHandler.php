<?php

namespace App\Application\PromptPreparation\Queries\ListFavoritePrompts;

use App\Application\PromptPreparation\Ports\FavoritePromptQueryService;

final readonly class ListFavoritePromptsHandler
{
    public function __construct(private FavoritePromptQueryService $favorites)
    {
    }

    public function handle(): ListFavoritePromptsResult
    {
        return new ListFavoritePromptsResult($this->favorites->list());
    }
}
