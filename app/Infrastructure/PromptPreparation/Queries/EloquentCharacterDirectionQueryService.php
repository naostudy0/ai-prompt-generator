<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\CharacterDirectionQueryService;
use App\Application\PromptPreparation\Queries\GetCharacterDirections\GetCharacterDirectionsResult;
use Illuminate\Support\Facades\DB;

final class EloquentCharacterDirectionQueryService implements CharacterDirectionQueryService
{
    public function getAll(): GetCharacterDirectionsResult
    {
        return new GetCharacterDirectionsResult(
            expressions: $this->getOptions('expression_prompts'),
            gazes: $this->getOptions('gaze_prompts'),
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
