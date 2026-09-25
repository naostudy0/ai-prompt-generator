<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\ModelFamily;
use App\Domain\PromptPreparation\Repositories\DuplicateModelFamilyName;
use App\Domain\PromptPreparation\Repositories\ModelFamilyDeletionDenied;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\ModelFamilyRecord;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentModelFamilyRepository implements ModelFamilyRepository
{
    public function save(?int $id, ModelFamily $family): int
    {
        $record = $id === null ? new ModelFamilyRecord() : ModelFamilyRecord::query()->findOrFail($id);
        try {
            $record->fill(['name' => $family->name, 'normalized_name' => $family->normalizedName])->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateModelFamilyName(previous: $exception);
        }

        return (int) $record->getKey();
    }

    public function delete(int $id): void
    {
        $record = ModelFamilyRecord::query()->findOrFail($id);
        if ($id === ModelFamily::ILLUSTRIOUS_ID || DB::table('loras')->where('model_family_id', $id)->exists()) {
            throw new ModelFamilyDeletionDenied();
        }
        DB::table('default_prompts')->where('model_family_id', $id)->delete();
        $record->delete();
    }

    public function ensureExists(int $id): void
    {
        ModelFamilyRecord::query()->findOrFail($id);
    }
}
