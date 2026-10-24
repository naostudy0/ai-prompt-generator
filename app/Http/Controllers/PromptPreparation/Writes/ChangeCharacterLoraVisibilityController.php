<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\ChangeCharacterLoraVisibility\ChangeCharacterLoraVisibilityHandler;
use App\Application\PromptPreparation\Writes\ChangeCharacterLoraVisibility\ChangeCharacterLoraVisibilityInput;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\ChangeCharacterLoraVisibilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class ChangeCharacterLoraVisibilityController extends Controller
{
    public function __invoke(int $lora, ChangeCharacterLoraVisibilityRequest $request, ChangeCharacterLoraVisibilityHandler $handler): JsonResponse
    {
        try {
            $result = $handler->handle(new ChangeCharacterLoraVisibilityInput(
                $lora,
                $request->boolean('isHidden'),
            ));
        } catch (LoraKindMismatch) {
            throw ValidationException::withMessages([
                'lora' => ['指定したLoRAは人物・キャラクターLoRAではありません。'],
            ]);
        }

        return response()->json($result);
    }
}
