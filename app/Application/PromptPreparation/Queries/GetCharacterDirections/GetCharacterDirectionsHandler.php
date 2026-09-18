<?php

namespace App\Application\PromptPreparation\Queries\GetCharacterDirections;

use App\Application\PromptPreparation\Ports\CharacterDirectionQueryService;

final readonly class GetCharacterDirectionsHandler
{
    public function __construct(private CharacterDirectionQueryService $queryService)
    {
    }

    public function handle(): GetCharacterDirectionsResult
    {
        return $this->queryService->getAll();
    }
}
