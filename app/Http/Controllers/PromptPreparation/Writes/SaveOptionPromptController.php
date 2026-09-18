<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveOptionPrompt\SaveOptionPromptHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveNamedPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveOptionPromptController extends Controller
{
    public function __invoke(SaveNamedPromptRequest $request, SaveOptionPromptHandler $handler, ?int $option = null): JsonResponse
    {
        $validated = $option === null
            ? $request->validate(['groupId' => ['required', 'integer', 'exists:option_prompt_groups,id']])
            : [];
        $result = $handler->handle(new SaveNamedPromptInput(
            id: $option,
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ), isset($validated['groupId']) ? (int) $validated['groupId'] : null);

        return response()->json($result, $option === null ? 201 : 200);
    }
}
