<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteOptionPrompt\DeleteOptionPromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteOptionPromptController extends Controller
{
    public function __invoke(int $option, DeleteOptionPromptHandler $handler): Response
    {
        $handler->handle($option);

        return response()->noContent();
    }
}
