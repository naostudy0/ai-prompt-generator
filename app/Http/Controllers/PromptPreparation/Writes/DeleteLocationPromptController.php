<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteLocationPrompt\DeleteLocationPromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteLocationPromptController extends Controller
{
    public function __invoke(int $location, DeleteLocationPromptHandler $handler): Response
    {
        $handler->handle($location);

        return response()->noContent();
    }
}
