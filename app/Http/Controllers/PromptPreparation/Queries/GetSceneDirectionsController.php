<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetSceneDirections\GetSceneDirectionsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetSceneDirectionsController extends Controller
{
    public function __invoke(GetSceneDirectionsHandler $handler): JsonResponse
    {
        return response()->json($handler->handle());
    }
}
