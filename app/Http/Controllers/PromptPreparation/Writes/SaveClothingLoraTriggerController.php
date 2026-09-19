<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveLoraTrigger\SaveLoraTriggerHandler;
use App\Application\PromptPreparation\Writes\SaveLoraTrigger\SaveLoraTriggerInput;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveClothingLoraTriggerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class SaveClothingLoraTriggerController extends Controller
{
    public function __invoke(
        SaveClothingLoraTriggerRequest $request,
        SaveLoraTriggerHandler $handler,
        ?int $clothingLoraTrigger = null,
    ): JsonResponse {
        try {
            $result = $handler->handle(new SaveLoraTriggerInput(
                id: $clothingLoraTrigger,
                loraId: $request->integer('loraId'),
                name: $request->string('name')->toString(),
                content: $request->string('content')->toString(),
            ), LoraKind::Clothing);
        } catch (LoraKindMismatch) {
            throw ValidationException::withMessages([
                'clothingLoraTrigger' => ['指定したトリガーは衣装LoRAのものではありません。'],
            ]);
        }

        return response()->json($result, $clothingLoraTrigger === null ? 201 : 200);
    }
}
