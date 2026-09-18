<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveOptionPromptGroup\SaveOptionPromptGroupHandler;
use App\Domain\PromptPreparation\Models\OptionSelectionMode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SaveOptionPromptGroupController extends Controller
{
    public function __invoke(Request $request, SaveOptionPromptGroupHandler $handler, ?int $group = null): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'selectionMode' => ['required', 'string', Rule::enum(OptionSelectionMode::class)],
        ]);
        $result = $handler->handle(
            $group,
            (string) $validated['name'],
            OptionSelectionMode::from((string) $validated['selectionMode']),
        );

        return response()->json($result, $group === null ? 201 : 200);
    }
}
