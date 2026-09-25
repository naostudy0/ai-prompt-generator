<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsResult;
use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\DefaultPromptRecord;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\ModelFamilyRecord;

final class EloquentDefaultPromptQueryService implements DefaultPromptQueryService
{
    public function get(int $modelFamilyId = 1): GetDefaultPromptsResult
    {
        ModelFamilyRecord::query()->findOrFail($modelFamilyId);
        /** @var array<string, string> $prompts */
        $prompts = DefaultPromptRecord::query()
            ->where('model_family_id', $modelFamilyId)
            ->pluck('content', 'polarity')
            ->all();

        return new GetDefaultPromptsResult(
            positive: $prompts[PromptPolarity::Positive->value] ?? '',
            negative: $prompts[PromptPolarity::Negative->value] ?? '',
        );
    }
}
