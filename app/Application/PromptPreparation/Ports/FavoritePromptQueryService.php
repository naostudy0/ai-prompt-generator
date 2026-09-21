<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\FavoritePromptDetail;
use App\Application\PromptPreparation\Queries\FavoritePromptSummary;

interface FavoritePromptQueryService
{
    /** @return list<FavoritePromptSummary> */
    public function list(): array;

    public function find(int $id): ?FavoritePromptDetail;
}
