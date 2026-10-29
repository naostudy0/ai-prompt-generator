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
            ->get(['id', 'option_prompt_group_id', 'position', 'name', 'content', 'negative_content'])->groupBy('option_prompt_group_id');
        $groups = DB::table('option_prompt_groups')->orderBy('position')
            ->get([
                'id',
                'option_category_id',
                'name',
                'selection_mode',
                'position',
                'system_key',
                'random_generation_role',
            ])
            ->map(function (object $group) use ($options): array {
                return [
                    'id' => (int) $group->id,
                    'categoryId' => $group->option_category_id === null ? null : (int) $group->option_category_id,
                    'name' => (string) $group->name,
                    'selectionMode' => (string) $group->selection_mode,
                    'position' => (int) $group->position,
                    'systemManaged' => $group->system_key !== null,
                    'randomGenerationRole' => $group->random_generation_role,
                    'options' => array_values($options->get($group->id, collect())->map(function (object $row): array {
                        $data = (array) $row;

                        return [
                        'id' => (int) $data['id'],
                        'position' => (int) $data['position'],
                            'name' => (string) $data['name'],
                            'content' => (string) $data['content'],
                            'negativeContent' => (string) $data['negative_content'],
                        ];
                    })->values()->all()),
                ];
            })->values()->all();

        return new GetPromptOptionsResult(groups: array_values($groups));
    }
}
