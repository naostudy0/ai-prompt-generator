<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\MoveOptionPrompt\MoveOptionPromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MoveOptionPromptController extends Controller
{
    public function __invoke(Request $request, MoveOptionPromptHandler $handler, int $option): JsonResponse
    {
        $validated = $request->validate([
            'targetGroupId' => ['required', 'integer', 'exists:option_prompt_groups,id'],
            'beforeOptionId' => ['nullable', 'integer'],
        ]);
        $handler->handle(
            $option,
            (int) $validated['targetGroupId'],
            isset($validated['beforeOptionId']) ? (int) $validated['beforeOptionId'] : null,
        );

        return response()->json(status: 204);
    }
}
