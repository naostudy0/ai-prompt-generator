<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\OptionCategory;

interface OptionCategoryRepository
{
    public function get(int $id): OptionCategory;

    public function getForUpdate(int $id): OptionCategory;

    public function nextPosition(): int;

    public function save(OptionCategory $category): OptionCategory;

    public function moveBefore(int $id, ?int $beforeId): void;

    public function delete(int $id): void;
}
