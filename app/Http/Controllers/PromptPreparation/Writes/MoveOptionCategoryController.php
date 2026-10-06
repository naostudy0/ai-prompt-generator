<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\MoveOptionCategory\MoveOptionCategoryHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MoveOptionCategoryController extends Controller
{
    public function __invoke(Request $request, MoveOptionCategoryHandler $handler, int $category): Response
    {
        $validated = $request->validate(['beforeCategoryId' => ['nullable', 'integer', 'min:1']]);
        $handler->handle($category, isset($validated['beforeCategoryId']) ? (int) $validated['beforeCategoryId'] : null);

        return response()->noContent();
    }
}
