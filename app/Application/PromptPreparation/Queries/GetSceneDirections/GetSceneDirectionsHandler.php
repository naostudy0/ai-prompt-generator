<?php

namespace App\Application\PromptPreparation\Queries\GetSceneDirections;

use App\Application\PromptPreparation\Ports\SceneDirectionQueryService;

final readonly class GetSceneDirectionsHandler
{
    public function __construct(private SceneDirectionQueryService $queryService)
    {
    }

    public function handle(): GetSceneDirectionsResult
    {
        return $this->queryService->getAll();
    }
}
