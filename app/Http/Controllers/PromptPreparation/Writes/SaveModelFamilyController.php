<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveModelFamily\SaveModelFamilyHandler;
use App\Domain\PromptPreparation\Repositories\DuplicateModelFamilyName;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SaveModelFamilyController extends Controller
{
    public function __invoke(Request $request, SaveModelFamilyHandler $handler, ?int $family = null): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        try {
            $result = $handler->handle($family, $validated['name']);
        } catch (DuplicateModelFamilyName) {
            throw ValidationException::withMessages(['name' => ['同じ名前の系統が登録されています。']]);
        }

        return response()->json($result, $family === null ? 201 : 200);
    }
}
