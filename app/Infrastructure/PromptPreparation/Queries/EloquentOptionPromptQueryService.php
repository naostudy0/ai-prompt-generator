<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\OptionPromptQueryService;
use App\Application\PromptPreparation\Queries\GetPromptOptions\GetPromptOptionsResult;
use Illuminate\Support\Facades\DB;

final class EloquentOptionPromptQueryService implements OptionPromptQueryService
{
    public function getAll(): GetPromptOptionsResult
    {
        $options = DB::table('option_prompts')->orderBy('position')
            ->get(['id', 'option_prompt_group_id', 'position', 'name', 'content'])->groupBy('option_prompt_group_id');
        $groups = DB::table('option_prompt_groups')->orderBy('position')
            ->get(['id', 'name', 'selection_mode', 'position'])
            ->map(function (object $group) use ($options): array {
                return [
                    'id' => (int) $group->id,
                    'name' => (string) $group->name,
                    'selectionMode' => (string) $group->selection_mode,
                    'position' => (int) $group->position,
                    'options' => array_values($options->get($group->id, collect())->map(function (object $row): array {
                        $data = (array) $row;

                        return [
                        'id' => (int) $data['id'],
                        'position' => (int) $data['position'],
                            'name' => (string) $data['name'],
                            'content' => (string) $data['content'],
                        ];
                    })->values()->all()),
                ];
            })->values()->all();

        return new GetPromptOptionsResult(groups: array_values($groups));
    }
}
