<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\GenerationSettingKind;
use App\Domain\PromptPreparation\Models\GenerationSettingOption;
use App\Domain\PromptPreparation\Repositories\DuplicateGenerationSettingOption;
use App\Domain\PromptPreparation\Repositories\GenerationSettingOptionInUse;
use App\Domain\PromptPreparation\Repositories\GenerationSettingOptionRepository;
use App\Domain\PromptPreparation\Repositories\InvalidGenerationSettingSelection;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\GenerationSettingOptionRecord;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentGenerationSettingOptionRepository implements GenerationSettingOptionRepository
{
    public function listForFamily(int $modelFamilyId): array
    {
        return array_values(GenerationSettingOptionRecord::query()
            ->where('model_family_id', $modelFamilyId)
            ->orderBy('kind')->orderBy('value')->orderBy('id')->get()
            ->map(fn (GenerationSettingOptionRecord $record): GenerationSettingOption => $this->toDomain($record))
            ->all());
    }

    public function save(GenerationSettingOption $option): GenerationSettingOption
    {
        $record = $option->id === null
            ? new GenerationSettingOptionRecord()
            : GenerationSettingOptionRecord::query()->findOrFail($option->id);
        if ($record->exists && ((int) $record->model_family_id !== $option->modelFamilyId || $record->kind !== $option->kind->value)) {
            throw new InvalidGenerationSettingSelection('Generation setting family and kind cannot be changed.');
        }
        try {
            $record->fill([
                'model_family_id' => $option->modelFamilyId,
                'kind' => $option->kind->value,
                'value' => $option->value,
            ])->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateGenerationSettingOption(previous: $exception);
        }

        return $this->toDomain($record);
    }

    public function delete(int $id): void
    {
        $record = GenerationSettingOptionRecord::query()->findOrFail($id);
        try {
            DB::table('generation_setting_options')->where('id', $id)->delete();
        } catch (QueryException $exception) {
            throw new GenerationSettingOptionInUse(previous: $exception);
        }
    }

    public function ensureMatches(int $id, int $modelFamilyId, GenerationSettingKind $kind): void
    {
        $exists = GenerationSettingOptionRecord::query()
            ->whereKey($id)->where('model_family_id', $modelFamilyId)->where('kind', $kind->value)->exists();
        if (!$exists) {
            throw new InvalidGenerationSettingSelection("{$kind->value} does not belong to the selected model family.");
        }
    }

    private function toDomain(GenerationSettingOptionRecord $record): GenerationSettingOption
    {
        return new GenerationSettingOption(
            id: (int) $record->getKey(),
            modelFamilyId: (int) $record->model_family_id,
            kind: GenerationSettingKind::from((string) $record->kind),
            value: (string) $record->value,
        );
    }
}
