<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\ListFavoritePrompts\ListFavoritePromptsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListFavoritePromptsController extends Controller
{
    public function __invoke(ListFavoritePromptsHandler $handler): JsonResponse
    {
        return response()->json(['favorites' => $handler->handle()->favorites]);
    }
}
