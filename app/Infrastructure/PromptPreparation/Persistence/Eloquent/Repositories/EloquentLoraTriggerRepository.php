<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\Lora\LoraTrigger;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\LoraTriggerRecord;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\LoraRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentLoraTriggerRepository implements LoraTriggerRepository
{
    public function save(LoraTrigger $trigger, LoraKind $kind): LoraTrigger
    {
        $parent = LoraRecord::query()->findOrFail($trigger->loraId);
        if ($parent->kind !== $kind->value) {
            throw new LoraKindMismatch('The LoRA kind does not match the requested operation.');
        }
        $record = $trigger->id === null
            ? new LoraTriggerRecord()
            : LoraTriggerRecord::query()->findOrFail($trigger->id);
        if ($record->exists && $record->lora()->firstOrFail()->kind !== $kind->value) {
            throw new LoraKindMismatch('The LoRA trigger kind does not match the requested operation.');
        }

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

    public function delete(int $id, LoraKind $kind): void
    {
        $record = LoraTriggerRecord::query()->findOrFail($id);
        if ($record->lora()->firstOrFail()->kind !== $kind->value) {
            throw new LoraKindMismatch('The LoRA trigger kind does not match the requested operation.');
        }
        $deleted = $record->delete() ? 1 : 0;

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(LoraTriggerRecord::class, [$id]);
        }
    }
}
