<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use App\Domain\PromptPreparation\Models\OptionSelectionMode;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OptionPromptGroupRecord;

final class EloquentOptionPromptGroupRepository implements OptionPromptGroupRepository
{
    public function get(int $id): OptionPromptGroup
    {
        return $this->toDomain(OptionPromptGroupRecord::query()->findOrFail($id));
    }

    public function nextPosition(): int
    {
        return ((int) OptionPromptGroupRecord::query()->lockForUpdate()->max('position')) + 1;
    }

    public function save(OptionPromptGroup $group): OptionPromptGroup
    {
        $record = $group->id === null
            ? new OptionPromptGroupRecord()
            : OptionPromptGroupRecord::query()->findOrFail($group->id);
        $record->fill([
            'name' => $group->name,
            'selection_mode' => $group->selectionMode->value,
            'position' => $group->position,
        ])->save();

        return $this->toDomain($record);
    }

    public function moveBefore(int $id, ?int $beforeId): void
    {
        $records = OptionPromptGroupRecord::query()->orderBy('position')->get();
        $moving = $records->first(fn (OptionPromptGroupRecord $record): bool => $record->getKey() === $id);
        if (! $moving instanceof OptionPromptGroupRecord) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(
                OptionPromptGroupRecord::class,
                [$id],
            );
        }
        $ordered = $records->reject(fn (OptionPromptGroupRecord $record): bool => $record->getKey() === $id)->values();
        $index = $beforeId === null ? $ordered->count() : $ordered->search(
            fn (OptionPromptGroupRecord $record): bool => $record->getKey() === $beforeId,
        );
        if ($index === false) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(
                OptionPromptGroupRecord::class,
                [$beforeId],
            );
        }
        $ordered->splice((int) $index, 0, [$moving]);
        foreach ($ordered as $position => $record) {
            $record->update(['position' => -($position + 1)]);
        }
        foreach ($ordered as $position => $record) {
            $record->update(['position' => $position + 1]);
        }
    }

    private function toDomain(OptionPromptGroupRecord $record): OptionPromptGroup
    {
        return new OptionPromptGroup(
            id: $record->getKey(),
            name: $record->name,
            selectionMode: OptionSelectionMode::from($record->selection_mode),
            position: $record->position,
        );
    }
}
