<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\CompositionPrompt;

interface CompositionPromptRepository
{
    public function save(CompositionPrompt $prompt): CompositionPrompt;

    public function delete(int $id): void;
}
