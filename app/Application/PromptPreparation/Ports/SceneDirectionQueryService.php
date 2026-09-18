<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\GetSceneDirections\GetSceneDirectionsResult;

interface SceneDirectionQueryService
{
    public function getAll(): GetSceneDirectionsResult;
}
