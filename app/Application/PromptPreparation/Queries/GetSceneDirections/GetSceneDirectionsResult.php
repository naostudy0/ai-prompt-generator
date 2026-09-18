<?php

namespace App\Application\PromptPreparation\Queries\GetSceneDirections;

final readonly class GetSceneDirectionsResult
{
    /**
     * @param list<array{id: int, name: string, content: string}> $locations
     * @param list<array{id: int, name: string, content: string}> $compositions
     * @param list<array{id: int, name: string, content: string}> $actions
     */
    public function __construct(public array $locations, public array $compositions, public array $actions)
    {
    }
}
