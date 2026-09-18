<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\OptionPromptQueryService;
use App\Application\PromptPreparation\Queries\GetPromptOptions\GetPromptOptionsResult;
use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use Illuminate\Support\Facades\DB;

final class EloquentOptionPromptQueryService implements OptionPromptQueryService
{
    public function getAll(): GetPromptOptionsResult
    {
        $options = DB::table('option_prompts')->orderBy('name')->orderBy('id')
            ->get(['id', 'option_prompt_group_id', 'name', 'content'])->groupBy('option_prompt_group_id');
        $groups = DB::table('option_prompt_groups')->orderBy('position')->get(['id', 'position'])
            ->map(function (object $group) use ($options): array {
                $model = new OptionPromptGroup((int) $group->id, (int) $group->position);

                return [
                    'id' => (int) $group->id,
                    'position' => (int) $group->position,
                    'label' => $model->label(),
                    'options' => array_values($options->get($group->id, collect())->map(function (object $row): array {
                        $data = (array) $row;

                        return [
                            'id' => (int) $data['id'],
                            'name' => (string) $data['name'],
                            'content' => (string) $data['content'],
                        ];
                    })->values()->all()),
                ];
            })->values()->all();

        return new GetPromptOptionsResult(groups: array_values($groups));
    }
}
