<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\GazePrompt;

interface GazePromptRepository
{
    public function save(GazePrompt $prompt): GazePrompt;

    public function delete(int $id): void;
}
