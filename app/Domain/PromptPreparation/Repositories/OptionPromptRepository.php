<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\OptionPrompt;

interface OptionPromptRepository
{
    public function save(OptionPrompt $prompt): OptionPrompt;

    public function delete(int $id): void;
}
