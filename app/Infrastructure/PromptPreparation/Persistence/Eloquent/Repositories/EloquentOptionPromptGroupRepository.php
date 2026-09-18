<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\OptionPromptGroupRecord;

final class EloquentOptionPromptGroupRepository implements OptionPromptGroupRepository
{
    public function nextPosition(): int
    {
        return ((int) OptionPromptGroupRecord::query()->lockForUpdate()->max('position')) + 1;
    }

    public function save(OptionPromptGroup $group): OptionPromptGroup
    {
        $record = OptionPromptGroupRecord::query()->create(['position' => $group->position]);

        return new OptionPromptGroup(id: $record->getKey(), position: $record->position);
    }
}
