<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\DefaultPromptRecord;

final class EloquentDefaultPromptRepository implements DefaultPromptRepository
{
    public function save(DefaultPrompt $defaultPrompt): void
    {
        DefaultPromptRecord::query()->updateOrCreate(
            ['polarity' => $defaultPrompt->polarity->value],
            ['content' => $defaultPrompt->text->value],
        );
    }
}
