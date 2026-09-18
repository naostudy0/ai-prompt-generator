<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveGazePrompt\SaveGazePromptHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveNamedPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveGazePromptController extends Controller
{
    public function __invoke(SaveNamedPromptRequest $request, SaveGazePromptHandler $handler, ?int $gaze = null): JsonResponse
    {
        $result = $handler->handle(new SaveNamedPromptInput(
            id: $gaze,
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ));

        return response()->json($result, $gaze === null ? 201 : 200);
    }
}
