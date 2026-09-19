<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetClothingLoraOptions\GetClothingLoraOptionsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetClothingLoraOptionsController extends Controller
{
    public function __invoke(GetClothingLoraOptionsHandler $handler): JsonResponse
    {
        $result = $handler->handle();

        return response()->json(['loras' => $result->loras, 'triggers' => $result->triggers]);
    }
}
