<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use Illuminate\Support\Facades\DB;

final class EloquentDefaultPromptRepository implements DefaultPromptRepository
{
    public function save(DefaultPrompt $defaultPrompt): void
    {
        DB::table('default_prompts')->updateOrInsert(
            ['model_family_id' => $defaultPrompt->modelFamilyId, 'polarity' => $defaultPrompt->polarity->value],
            ['content' => $defaultPrompt->text->value, 'updated_at' => now()],
        );
    }
}
