<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteLora\DeleteLoraHandler;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class DeleteLoraController extends Controller
{
    public function __invoke(int $lora, DeleteLoraHandler $handler): Response
    {
        try {
            $handler->handle($lora, LoraKind::Character);
        } catch (LoraKindMismatch) {
            throw ValidationException::withMessages([
                'lora' => ['指定したLoRAは人物・キャラクターLoRAではありません。'],
            ]);
        }

        return response()->noContent();
    }
}
