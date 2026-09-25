<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\ListModelFamilies\ListModelFamiliesHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListModelFamiliesController extends Controller
{
    public function __invoke(ListModelFamiliesHandler $handler): JsonResponse
    {
        return response()->json($handler->handle());
    }
}
