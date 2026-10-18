<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\MoveOptionPromptGroup\MoveOptionPromptGroupHandler;
use App\Domain\PromptPreparation\Exceptions\SystemManagedOptionPromptGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MoveOptionPromptGroupController extends Controller
{
    public function __invoke(Request $request, MoveOptionPromptGroupHandler $handler, int $group): JsonResponse
    {
        $validated = $request->validate(['beforeGroupId' => ['nullable', 'integer']]);
        try {
            $handler->handle($group, isset($validated['beforeGroupId']) ? (int) $validated['beforeGroupId'] : null);
        } catch (SystemManagedOptionPromptGroup) {
            return response()->json(['message' => 'このブロックはシステム管理のため変更できません。'], 409);
        }

        return response()->json(status: 204);
    }
}
