<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\OutfitPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OutfitPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OutfitPromptRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentOutfitPromptRepository implements OutfitPromptRepository
{
    public function save(OutfitPrompt $outfit): OutfitPrompt
    {
        $record = $outfit->id === null
            ? new OutfitPromptRecord()
            : OutfitPromptRecord::query()->findOrFail($outfit->id);

        $record->fill([
            'lora_id' => $outfit->loraId,
            'name' => $outfit->name,
            'content' => $outfit->content->value,
        ])->save();

        return new OutfitPrompt(
            id: $record->getKey(),
            loraId: $record->lora_id,
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = OutfitPromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(OutfitPromptRecord::class, [$id]);
        }
    }
}
