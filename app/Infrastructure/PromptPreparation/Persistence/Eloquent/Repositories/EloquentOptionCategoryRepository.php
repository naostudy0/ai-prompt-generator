<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\OptionCategory;
use App\Domain\PromptPreparation\Repositories\OptionCategoryRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OptionCategoryRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentOptionCategoryRepository implements OptionCategoryRepository
{
    public function get(int $id): OptionCategory
    {
        return $this->toDomain(OptionCategoryRecord::query()->findOrFail($id));
    }

    public function getForUpdate(int $id): OptionCategory
    {
        return $this->toDomain(OptionCategoryRecord::query()->lockForUpdate()->findOrFail($id));
    }

    public function nextPosition(): int
    {
        return ((int) OptionCategoryRecord::query()->lockForUpdate()->max('position')) + 1;
    }

    public function save(OptionCategory $category): OptionCategory
    {
        $record = $category->id === null
            ? new OptionCategoryRecord()
            : OptionCategoryRecord::query()->findOrFail($category->id);
        $record->fill(['name' => $category->name, 'position' => $category->position])->save();

        return $this->toDomain($record);
    }

    public function moveBefore(int $id, ?int $beforeId): void
    {
        $records = OptionCategoryRecord::query()->orderBy('position')->lockForUpdate()->get();
        $moving = $records->first(fn (OptionCategoryRecord $record): bool => $record->getKey() === $id);
        if (! $moving instanceof OptionCategoryRecord) {
            throw (new ModelNotFoundException())->setModel(OptionCategoryRecord::class, [$id]);
        }
        $ordered = $records->reject(fn (OptionCategoryRecord $record): bool => $record->getKey() === $id)->values();
        $index = $beforeId === null ? $ordered->count() : $ordered->search(
            fn (OptionCategoryRecord $record): bool => $record->getKey() === $beforeId,
        );
        if ($index === false) {
            throw (new ModelNotFoundException())->setModel(OptionCategoryRecord::class, [$beforeId]);
        }
        $ordered->splice((int) $index, 0, [$moving]);
        $offset = ((int) $records->max('position')) + $records->count() + 1;
        foreach ($ordered as $position => $record) {
            $record->update(['position' => $offset + $position]);
        }
        foreach ($ordered as $position => $record) {
            $record->update(['position' => $position + 1]);
        }
    }

    public function delete(int $id): void
    {
        $record = OptionCategoryRecord::query()->lockForUpdate()->findOrFail($id);
        $position = (int) $record->position;
        $record->delete();
        OptionCategoryRecord::query()->where('position', '>', $position)->orderBy('position')
            ->get()->each(fn (OptionCategoryRecord $item) => $item->update(['position' => $item->position - 1]));
    }

    private function toDomain(OptionCategoryRecord $record): OptionCategory
    {
        return new OptionCategory($record->getKey(), $record->name, $record->position);
    }
}
