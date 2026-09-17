<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteOutfitPrompt\DeleteOutfitPromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteOutfitPromptController extends Controller
{
    public function __invoke(int $outfit, DeleteOutfitPromptHandler $handler): Response
    {
        $handler->handle($outfit);

        return response()->noContent();
    }
}
