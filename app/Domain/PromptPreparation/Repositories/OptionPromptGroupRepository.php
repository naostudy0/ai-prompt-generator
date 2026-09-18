<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\OptionPromptGroup;

interface OptionPromptGroupRepository
{
    public function get(int $id): OptionPromptGroup;

    public function nextPosition(): int;

    public function save(OptionPromptGroup $group): OptionPromptGroup;

    public function moveBefore(int $id, ?int $beforeId): void;
}
