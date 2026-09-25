<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\ModelFamily;

interface ModelFamilyRepository
{
    public function save(?int $id, ModelFamily $family): int;

    public function delete(int $id): void;

    public function ensureExists(int $id): void;
}
