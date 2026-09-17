<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetDefaultPromptsController extends Controller
{
    public function __invoke(GetDefaultPromptsHandler $handler): JsonResponse
    {
        $result = $handler->handle();

        return response()->json([
            'positive' => $result->positive,
            'negative' => $result->negative,
        ]);
    }
}
