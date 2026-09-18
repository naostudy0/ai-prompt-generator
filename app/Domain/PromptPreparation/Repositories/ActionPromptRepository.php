<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\ActionPrompt;

interface ActionPromptRepository
{
    public function save(ActionPrompt $prompt): ActionPrompt;

    public function delete(int $id): void;
}
