<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveLora\SaveLoraHandler;
use App\Application\PromptPreparation\Writes\SaveLora\SaveLoraInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveLoraRequest;
use App\Domain\PromptPreparation\Repositories\DuplicateLoraFileName;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class SaveLoraController extends Controller
{
    public function __invoke(SaveLoraRequest $request, SaveLoraHandler $handler, ?int $lora = null): JsonResponse
    {
        try {
            $result = $handler->handle(new SaveLoraInput(
                id: $lora,
                name: $request->string('name')->toString(),
                fileName: $request->string('fileName')->toString(),
                recommendedStrength: $request->float('recommendedStrength'),
            ));
        } catch (DuplicateLoraFileName) {
            throw ValidationException::withMessages([
                'fileName' => ['同じファイル名のLoRAがすでに登録されています。'],
            ]);
        }

        return response()->json($result, $lora === null ? 201 : 200);
    }
}
