<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\ChangeOptionPromptGroupCategory\ChangeOptionPromptGroupCategoryHandler;
use App\Domain\PromptPreparation\Exceptions\SystemManagedOptionPromptGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChangeOptionPromptGroupCategoryController extends Controller
{
    public function __invoke(Request $request, ChangeOptionPromptGroupCategoryHandler $handler, int $group): JsonResponse
    {
        $validated = $request->validate(['categoryId' => ['nullable', 'integer', 'min:1']]);

        try {
            return response()->json($handler->handle(
                $group,
                isset($validated['categoryId']) ? (int) $validated['categoryId'] : null,
            ));
        } catch (SystemManagedOptionPromptGroup) {
            return response()->json(['message' => 'このブロックはシステム管理のため変更できません。'], 409);
        }
    }
}
