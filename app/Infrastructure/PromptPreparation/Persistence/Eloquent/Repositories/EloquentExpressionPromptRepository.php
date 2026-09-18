<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\ExpressionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\ExpressionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\ExpressionPromptRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentExpressionPromptRepository implements ExpressionPromptRepository
{
    public function save(ExpressionPrompt $prompt): ExpressionPrompt
    {
        $record = $prompt->id === null
            ? new ExpressionPromptRecord()
            : ExpressionPromptRecord::query()->findOrFail($prompt->id);

        $record->fill(['name' => $prompt->name, 'content' => $prompt->content->value])->save();

        return new ExpressionPrompt(
            id: $record->getKey(),
            name: $record->name,
            content: PromptText::fromInput($record->content),
        );
    }

    public function delete(int $id): void
    {
        $deleted = ExpressionPromptRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(ExpressionPromptRecord::class, [$id]);
        }
    }
}
