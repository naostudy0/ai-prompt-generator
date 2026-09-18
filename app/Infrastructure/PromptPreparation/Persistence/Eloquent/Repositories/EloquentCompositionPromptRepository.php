<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\CompositionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\CompositionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\CompositionPromptRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentCompositionPromptRepository implements CompositionPromptRepository
{
    public function save(CompositionPrompt $prompt): CompositionPrompt
    {
        $record = $prompt->id === null
            ? new CompositionPromptRecord()
            : CompositionPromptRecord::query()->findOrFail($prompt->id);

        $record->fill(['name' => $prompt->name, 'content' => $prompt->content->value])->save();

        return new CompositionPrompt(
            id: $record->getKey(),
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = CompositionPromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(CompositionPromptRecord::class, [$id]);
        }
    }
}
