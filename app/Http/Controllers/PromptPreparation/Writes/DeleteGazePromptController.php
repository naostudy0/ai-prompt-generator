<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteGazePrompt\DeleteGazePromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteGazePromptController extends Controller
{
    public function __invoke(int $gaze, DeleteGazePromptHandler $handler): Response
    {
        $handler->handle($gaze);

        return response()->noContent();
    }
}
