<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;

interface DefaultPromptRepository
{
    public function save(DefaultPrompt $defaultPrompt): void;
}
