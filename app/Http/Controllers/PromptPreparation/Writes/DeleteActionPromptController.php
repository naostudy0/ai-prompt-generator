<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteActionPrompt\DeleteActionPromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteActionPromptController extends Controller
{
    public function __invoke(int $action, DeleteActionPromptHandler $handler): Response
    {
        $handler->handle($action);

        return response()->noContent();
    }
}
