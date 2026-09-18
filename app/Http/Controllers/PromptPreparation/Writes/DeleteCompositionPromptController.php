<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteCompositionPrompt\DeleteCompositionPromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteCompositionPromptController extends Controller
{
    public function __invoke(int $composition, DeleteCompositionPromptHandler $handler): Response
    {
        $handler->handle($composition);

        return response()->noContent();
    }
}
