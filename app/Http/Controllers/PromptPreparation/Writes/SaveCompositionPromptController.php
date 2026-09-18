<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveCompositionPrompt\SaveCompositionPromptHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveNamedPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveCompositionPromptController extends Controller
{
    public function __invoke(SaveNamedPromptRequest $request, SaveCompositionPromptHandler $handler, ?int $composition = null): JsonResponse
    {
        $result = $handler->handle(new SaveNamedPromptInput(
            id: $composition,
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ));

        return response()->json($result, $composition === null ? 201 : 200);
    }
}
