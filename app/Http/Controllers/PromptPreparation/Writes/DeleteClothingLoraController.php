<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteLora\DeleteLoraHandler;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class DeleteClothingLoraController extends Controller
{
    public function __invoke(int $clothingLora, DeleteLoraHandler $handler): Response
    {
        try {
            $handler->handle($clothingLora, LoraKind::Clothing);
        } catch (LoraKindMismatch) {
            throw ValidationException::withMessages([
                'clothingLora' => ['指定したLoRAは衣装LoRAではありません。'],
            ]);
        }

        return response()->noContent();
    }
}
