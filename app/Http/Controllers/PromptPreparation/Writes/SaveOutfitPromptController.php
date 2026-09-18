<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveOutfitPrompt\SaveOutfitPromptHandler;
use App\Application\PromptPreparation\Writes\SaveOutfitPrompt\SaveOutfitPromptInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveOutfitPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveOutfitPromptController extends Controller
{
    public function __invoke(SaveOutfitPromptRequest $request, SaveOutfitPromptHandler $handler, ?int $outfit = null): JsonResponse
    {
        $result = $handler->handle(new SaveOutfitPromptInput(
            id: $outfit,
            loraId: $request->integer('loraId'),
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ));

        return response()->json($result, $outfit === null ? 201 : 200);
    }
}
