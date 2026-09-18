<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetCharacterDirections\GetCharacterDirectionsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetCharacterDirectionsController extends Controller
{
    public function __invoke(GetCharacterDirectionsHandler $handler): JsonResponse
    {
        return response()->json($handler->handle());
    }
}
