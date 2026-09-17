<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\OutfitPrompt;

interface OutfitPromptRepository
{
    public function save(OutfitPrompt $outfit): OutfitPrompt;

    public function delete(int $id): void;
}
