<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteLoraTrigger\DeleteLoraTriggerHandler;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class DeleteClothingLoraTriggerController extends Controller
{
    public function __invoke(int $clothingLoraTrigger, DeleteLoraTriggerHandler $handler): Response
    {
        try {
            $handler->handle($clothingLoraTrigger, LoraKind::Clothing);
        } catch (LoraKindMismatch) {
            throw ValidationException::withMessages([
                'clothingLoraTrigger' => ['指定したトリガーは衣装LoRAのものではありません。'],
            ]);
        }

        return response()->noContent();
    }
}
