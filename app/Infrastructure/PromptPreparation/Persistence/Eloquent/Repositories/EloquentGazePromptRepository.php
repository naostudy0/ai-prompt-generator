<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\GazePrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\GazePromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\GazePromptRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentGazePromptRepository implements GazePromptRepository
{
    public function save(GazePrompt $prompt): GazePrompt
    {
        $record = $prompt->id === null
            ? new GazePromptRecord()
            : GazePromptRecord::query()->findOrFail($prompt->id);

        $record->fill(['name' => $prompt->name, 'content' => $prompt->content->value])->save();

        return new GazePrompt(
            id: $record->getKey(),
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = GazePromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(GazePromptRecord::class, [$id]);
        }
    }
}
