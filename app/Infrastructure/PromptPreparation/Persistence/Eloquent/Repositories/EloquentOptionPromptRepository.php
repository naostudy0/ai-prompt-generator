<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OptionPromptRecord;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OptionPromptGroupRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentOptionPromptRepository implements OptionPromptRepository
{
    public function get(int $id): OptionPrompt
    {
        return $this->toDomain(OptionPromptRecord::query()->findOrFail($id));
    }

    public function nextPosition(int $groupId): int
    {
        OptionPromptGroupRecord::query()->findOrFail($groupId);

        return ((int) OptionPromptRecord::query()->where('option_prompt_group_id', $groupId)
            ->lockForUpdate()->max('position')) + 1;
    }

    public function save(OptionPrompt $prompt): OptionPrompt
    {
        $record = $prompt->id === null
            ? new OptionPromptRecord()
            : OptionPromptRecord::query()->findOrFail($prompt->id);

        $record->fill([
            'option_prompt_group_id' => $record->exists ? $record->option_prompt_group_id : $prompt->groupId,
            'name' => $prompt->name,
            'content' => $prompt->content->value,
            'position' => $prompt->position,
        ])->save();

        return $this->toDomain($record);
    }

    private function toDomain(OptionPromptRecord $record): OptionPrompt
    {
        return new OptionPrompt(
            id: $record->getKey(),
            groupId: $record->option_prompt_group_id,
            name: $record->name,
            content: PromptText::fromInput($record->content),
            position: $record->position,
        );
    }

    public function delete(int $id): void
    {
        $deleted = OptionPromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(OptionPromptRecord::class, [$id]);
        }
    }

    public function normalizePositions(int $groupId): void
    {
        $records = OptionPromptRecord::query()->where('option_prompt_group_id', $groupId)
            ->orderBy('position')->get();
        $this->storePositions($groupId, $records);
    }

    public function moveBefore(int $id, int $targetGroupId, ?int $beforeId): void
    {
        $moving = OptionPromptRecord::query()->findOrFail($id);
        OptionPromptGroupRecord::query()->findOrFail($targetGroupId);
        $sourceGroupId = $moving->option_prompt_group_id;
        $target = OptionPromptRecord::query()->where('option_prompt_group_id', $targetGroupId)
            ->orderBy('position')->get()->reject(
                fn (OptionPromptRecord $record): bool => $record->getKey() === $id,
            )->values();
        $index = $beforeId === null ? $target->count() : $target->search(
            fn (OptionPromptRecord $record): bool => $record->getKey() === $beforeId,
        );
        if ($index === false) {
            throw (new ModelNotFoundException())->setModel(OptionPromptRecord::class, [$beforeId]);
        }
        $target->splice((int) $index, 0, [$moving]);
        $this->storePositions($targetGroupId, $target);

        if ($sourceGroupId !== $targetGroupId) {
            $source = OptionPromptRecord::query()->where('option_prompt_group_id', $sourceGroupId)
                ->whereKeyNot($id)->orderBy('position')->get();
            $this->storePositions($sourceGroupId, $source);
        }
    }

    /** @param \Illuminate\Support\Collection<int, OptionPromptRecord> $records */
    private function storePositions(int $groupId, $records): void
    {
        foreach ($records as $position => $record) {
            $record->update(['position' => -($position + 1)]);
        }
        foreach ($records as $position => $record) {
            $record->update([
                'option_prompt_group_id' => $groupId,
                'position' => $position + 1,
            ]);
        }
    }
}
