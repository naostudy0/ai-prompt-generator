<?php

namespace App\Application\PromptPreparation\Queries\GetFavoritePrompt;

use App\Application\PromptPreparation\Ports\FavoritePromptQueryService;
use App\Application\PromptPreparation\Queries\FavoritePromptDetail;

final readonly class GetFavoritePromptHandler
{
    public function __construct(private FavoritePromptQueryService $favorites)
    {
    }

    public function handle(int $id): ?FavoritePromptDetail
    {
        return $this->favorites->find($id);
    }
}
