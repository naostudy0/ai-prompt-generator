<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveLocationPrompt\SaveLocationPromptHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveNamedPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveLocationPromptController extends Controller
{
    public function __invoke(SaveNamedPromptRequest $request, SaveLocationPromptHandler $handler, ?int $location = null): JsonResponse
    {
        $result = $handler->handle(new SaveNamedPromptInput(
            id: $location,
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ));

        return response()->json($result, $location === null ? 201 : 200);
    }
}
