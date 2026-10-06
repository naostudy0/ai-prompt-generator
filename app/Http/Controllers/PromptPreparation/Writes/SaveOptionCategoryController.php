<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveOptionCategory\SaveOptionCategoryHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SaveOptionCategoryController extends Controller
{
    public function __invoke(Request $request, SaveOptionCategoryHandler $handler, ?int $category = null): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'regex:/\S/u']]);

        return response()->json(
            $handler->handle($category, (string) $validated['name']),
            $category === null ? 201 : 200,
        );
    }
}
