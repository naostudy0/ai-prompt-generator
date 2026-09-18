<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetPromptOptions\GetPromptOptionsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetPromptOptionsController extends Controller
{
    public function __invoke(GetPromptOptionsHandler $handler): JsonResponse
    {
        return response()->json($handler->handle());
    }
}
