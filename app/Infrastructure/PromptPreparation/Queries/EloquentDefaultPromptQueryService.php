<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsResult;
use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\DefaultPromptRecord;

final class EloquentDefaultPromptQueryService implements DefaultPromptQueryService
{
    public function get(): GetDefaultPromptsResult
    {
        /** @var array<string, string> $prompts */
        $prompts = DefaultPromptRecord::query()
            ->pluck('content', 'polarity')
            ->all();

        return new GetDefaultPromptsResult(
            positive: $prompts[PromptPolarity::Positive->value] ?? '',
            negative: $prompts[PromptPolarity::Negative->value] ?? '',
        );
    }
}
