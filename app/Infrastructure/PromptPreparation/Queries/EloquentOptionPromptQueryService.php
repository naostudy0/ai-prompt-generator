<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\OptionPromptQueryService;
use App\Application\PromptPreparation\Queries\GetPromptOptions\GetPromptOptionsResult;
use Illuminate\Support\Facades\DB;

final class EloquentOptionPromptQueryService implements OptionPromptQueryService
{
    public function getAll(): GetPromptOptionsResult
    {
        $options = DB::table('option_prompts')->orderBy('name')->orderBy('id')->get(['id', 'name', 'content'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'content' => (string) $row->content,
            ])->values()->all();

        return new GetPromptOptionsResult(options: array_values($options));
    }
}
