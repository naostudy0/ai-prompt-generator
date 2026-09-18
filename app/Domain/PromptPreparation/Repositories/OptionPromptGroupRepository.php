<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\OptionPromptGroup;

interface OptionPromptGroupRepository
{
    public function nextPosition(): int;

    public function save(OptionPromptGroup $group): OptionPromptGroup;
}
