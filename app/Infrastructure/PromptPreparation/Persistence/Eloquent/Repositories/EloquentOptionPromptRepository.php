<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OptionPromptRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentOptionPromptRepository implements OptionPromptRepository
{
    public function save(OptionPrompt $prompt): OptionPrompt
    {
        $record = $prompt->id === null
            ? new OptionPromptRecord()
            : OptionPromptRecord::query()->findOrFail($prompt->id);

        $record->fill(['name' => $prompt->name, 'content' => $prompt->content->value])->save();

        return new OptionPrompt(
            id: $record->getKey(),
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = OptionPromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(OptionPromptRecord::class, [$id]);
        }
    }
}
