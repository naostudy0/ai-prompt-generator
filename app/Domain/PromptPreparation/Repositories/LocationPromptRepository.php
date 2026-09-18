<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\LocationPrompt;

interface LocationPromptRepository
{
    public function save(LocationPrompt $prompt): LocationPrompt;

    public function delete(int $id): void;
}
