<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\ExpressionPrompt;

interface ExpressionPromptRepository
{
    public function save(ExpressionPrompt $prompt): ExpressionPrompt;

    public function delete(int $id): void;
}
