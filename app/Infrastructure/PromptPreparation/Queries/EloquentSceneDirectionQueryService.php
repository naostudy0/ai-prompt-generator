<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\SceneDirectionQueryService;
use App\Application\PromptPreparation\Queries\GetSceneDirections\GetSceneDirectionsResult;
use Illuminate\Support\Facades\DB;

final class EloquentSceneDirectionQueryService implements SceneDirectionQueryService
{
    public function getAll(): GetSceneDirectionsResult
    {
        return new GetSceneDirectionsResult(
            locations: $this->getOptions('location_prompts'),
            compositions: $this->getOptions('composition_prompts'),
            actions: $this->getOptions('action_prompts'),
        );
    }

    /** @return list<array{id: int, name: string, content: string}> */
    private function getOptions(string $table): array
    {
        return array_values(DB::table($table)->orderBy('name')->orderBy('id')->get(['id', 'name', 'content'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'content' => (string) $row->content,
            ])->values()->all());
    }
}
