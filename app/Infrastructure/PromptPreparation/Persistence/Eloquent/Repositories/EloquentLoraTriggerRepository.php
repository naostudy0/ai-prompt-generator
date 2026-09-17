<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\Lora\LoraTrigger;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\LoraTriggerRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentLoraTriggerRepository implements LoraTriggerRepository
{
    public function save(LoraTrigger $trigger): LoraTrigger
    {
        $record = $trigger->id === null
            ? new LoraTriggerRecord()
            : LoraTriggerRecord::query()->findOrFail($trigger->id);

        $record->fill([
            'lora_id' => $trigger->loraId,
            'name' => $trigger->name,
            'content' => $trigger->content->value,
        ])->save();

        return new LoraTrigger(
            id: $record->getKey(),
            loraId: $record->lora_id,
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = LoraTriggerRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(LoraTriggerRecord::class, [$id]);
        }
    }
}
