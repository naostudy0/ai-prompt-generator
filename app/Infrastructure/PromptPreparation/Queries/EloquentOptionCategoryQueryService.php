<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\OptionCategoryQueryService;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OptionCategoryRecord;

final class EloquentOptionCategoryQueryService implements OptionCategoryQueryService
{
    public function all(): array
    {
        return array_values(OptionCategoryRecord::query()->orderBy('position')->get()
            ->map(static fn (OptionCategoryRecord $record): array => [
                'id' => (int) $record->getKey(),
                'name' => $record->name,
                'position' => $record->position,
            ])->all());
    }
}
