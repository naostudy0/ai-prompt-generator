<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteLora\DeleteLoraHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteLoraController extends Controller
{
    public function __invoke(int $lora, DeleteLoraHandler $handler): Response
    {
        $handler->handle($lora);

        return response()->noContent();
    }
}
