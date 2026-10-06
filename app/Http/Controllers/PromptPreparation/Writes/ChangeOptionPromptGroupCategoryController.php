<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\ChangeOptionPromptGroupCategory\ChangeOptionPromptGroupCategoryHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChangeOptionPromptGroupCategoryController extends Controller
{
    public function __invoke(Request $request, ChangeOptionPromptGroupCategoryHandler $handler, int $group): JsonResponse
    {
        $validated = $request->validate(['categoryId' => ['nullable', 'integer', 'min:1']]);

        return response()->json($handler->handle(
            $group,
            isset($validated['categoryId']) ? (int) $validated['categoryId'] : null,
        ));
    }
}
