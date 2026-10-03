<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\GenerationSettingKind;
use App\Domain\PromptPreparation\Models\GenerationSettingOption;

interface GenerationSettingOptionRepository
{
    /** @return list<GenerationSettingOption> */
    public function listForFamily(int $modelFamilyId): array;

    public function save(GenerationSettingOption $option): GenerationSettingOption;

    public function delete(int $id): void;

    public function ensureMatches(int $id, int $modelFamilyId, GenerationSettingKind $kind): void;

}
