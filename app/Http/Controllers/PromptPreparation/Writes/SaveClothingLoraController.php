<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveLora\SaveLoraHandler;
use App\Application\PromptPreparation\Writes\SaveLora\SaveLoraInput;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Domain\PromptPreparation\Repositories\DuplicateLoraFileName;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveClothingLoraRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class SaveClothingLoraController extends Controller
{
    public function __invoke(
        SaveClothingLoraRequest $request,
        SaveLoraHandler $handler,
        ?int $clothingLora = null,
    ): JsonResponse {
        try {
            $result = $handler->handle(new SaveLoraInput(
                id: $clothingLora,
                name: $request->string('name')->toString(),
                fileName: $request->string('fileName')->toString(),
                recommendedStrength: $request->float('recommendedStrength'),
            ), LoraKind::Clothing);
        } catch (DuplicateLoraFileName) {
            throw ValidationException::withMessages([
                'fileName' => ['同じファイル名のLoRAがすでに登録されています。'],
            ]);
        } catch (LoraKindMismatch) {
            throw ValidationException::withMessages([
                'clothingLora' => ['指定したLoRAは衣装LoRAではありません。'],
            ]);
        }

        return response()->json($result, $clothingLora === null ? 201 : 200);
    }
}
