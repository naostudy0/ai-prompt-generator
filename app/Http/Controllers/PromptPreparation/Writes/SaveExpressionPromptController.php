<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveExpressionPrompt\SaveExpressionPromptHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveNamedPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveExpressionPromptController extends Controller
{
    public function __invoke(SaveNamedPromptRequest $request, SaveExpressionPromptHandler $handler, ?int $expression = null): JsonResponse
    {
        $result = $handler->handle(new SaveNamedPromptInput(
            id: $expression,
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ));

        return response()->json($result, $expression === null ? 201 : 200);
    }
}
