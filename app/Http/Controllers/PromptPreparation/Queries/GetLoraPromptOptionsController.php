<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\CharacterLoraVisibility;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\GetLoraPromptOptionsRequest;
use Illuminate\Http\JsonResponse;

final class GetLoraPromptOptionsController extends Controller
{
    public function __invoke(GetLoraPromptOptionsRequest $request, GetLoraPromptOptionsHandler $handler): JsonResponse
    {
        $result = $handler->handle(CharacterLoraVisibility::from($request->string('visibility', 'visible')->toString()));

        return response()->json([
            'loras' => $result->loras,
            'triggers' => $result->triggers,
            'outfits' => $result->outfits,
        ]);
    }
}
