<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\MoveOptionPromptGroup\MoveOptionPromptGroupHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MoveOptionPromptGroupController extends Controller
{
    public function __invoke(Request $request, MoveOptionPromptGroupHandler $handler, int $group): JsonResponse
    {
        $validated = $request->validate(['beforeGroupId' => ['nullable', 'integer']]);
        $handler->handle($group, isset($validated['beforeGroupId']) ? (int) $validated['beforeGroupId'] : null);

        return response()->json(status: 204);
    }
}
