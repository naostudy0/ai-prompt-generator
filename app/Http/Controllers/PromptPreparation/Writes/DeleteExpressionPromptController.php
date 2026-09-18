<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteExpressionPrompt\DeleteExpressionPromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteExpressionPromptController extends Controller
{
    public function __invoke(int $expression, DeleteExpressionPromptHandler $handler): Response
    {
        $handler->handle($expression);

        return response()->noContent();
    }
}
