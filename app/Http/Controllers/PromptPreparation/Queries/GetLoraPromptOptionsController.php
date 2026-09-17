<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetLoraPromptOptionsController extends Controller
{
    public function __invoke(GetLoraPromptOptionsHandler $handler): JsonResponse
    {
        $result = $handler->handle();

        return response()->json([
            'loras' => $result->loras,
            'triggers' => $result->triggers,
            'outfits' => $result->outfits,
        ]);
    }
}
