<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\ActionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\ActionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\ActionPromptRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentActionPromptRepository implements ActionPromptRepository
{
    public function save(ActionPrompt $prompt): ActionPrompt
    {
        $record = $prompt->id === null
            ? new ActionPromptRecord()
            : ActionPromptRecord::query()->findOrFail($prompt->id);

        $record->fill(['name' => $prompt->name, 'content' => $prompt->content->value])->save();

        return new ActionPrompt(
            id: $record->getKey(),
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = ActionPromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(ActionPromptRecord::class, [$id]);
        }
    }
}
