<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveActionPrompt\SaveActionPromptHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveNamedPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveActionPromptController extends Controller
{
    public function __invoke(SaveNamedPromptRequest $request, SaveActionPromptHandler $handler, ?int $action = null): JsonResponse
    {
        $result = $handler->handle(new SaveNamedPromptInput(
            id: $action,
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ));

        return response()->json($result, $action === null ? 201 : 200);
    }
}
