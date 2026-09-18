<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\AddOptionPromptGroup\AddOptionPromptGroupHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class AddOptionPromptGroupController extends Controller
{
    public function __invoke(AddOptionPromptGroupHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(), 201);
    }
}
