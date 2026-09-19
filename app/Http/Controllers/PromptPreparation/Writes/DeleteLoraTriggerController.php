<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteLoraTrigger\DeleteLoraTriggerHandler;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Exceptions\LoraKindMismatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class DeleteLoraTriggerController extends Controller
{
    public function __invoke(int $trigger, DeleteLoraTriggerHandler $handler): Response
    {
        try {
            $handler->handle($trigger, LoraKind::Character);
        } catch (LoraKindMismatch) {
            throw ValidationException::withMessages([
                'trigger' => ['指定したトリガーは人物・キャラクターLoRAのものではありません。'],
            ]);
        }

        return response()->noContent();
    }
}
