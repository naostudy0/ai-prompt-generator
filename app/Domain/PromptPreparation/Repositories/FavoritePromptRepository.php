<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\FavoritePrompt;

interface FavoritePromptRepository
{
    public function get(int $id): ?FavoritePrompt;

    public function save(FavoritePrompt $favorite): FavoritePrompt;

    public function delete(int $id): void;
}
