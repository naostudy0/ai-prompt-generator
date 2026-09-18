<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\LocationPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\LocationPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\LocationPromptRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentLocationPromptRepository implements LocationPromptRepository
{
    public function save(LocationPrompt $prompt): LocationPrompt
    {
        $record = $prompt->id === null
            ? new LocationPromptRecord()
            : LocationPromptRecord::query()->findOrFail($prompt->id);

        $record->fill(['name' => $prompt->name, 'content' => $prompt->content->value])->save();

        return new LocationPrompt(
            id: $record->getKey(),
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = LocationPromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(LocationPromptRecord::class, [$id]);
        }
    }
}
