<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\ModelFamilyQueryService;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\ModelFamilyRecord;

final class EloquentModelFamilyQueryService implements ModelFamilyQueryService
{
    public function all(): array
    {
        return array_values(ModelFamilyRecord::query()->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(fn (ModelFamilyRecord $record): array => [
                'id' => (int) $record->getKey(),
                'name' => (string) $record->getAttribute('name'),
            ])->all());
    }
}
