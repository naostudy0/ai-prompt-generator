<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteComfyUiWorkflow\DeleteComfyUiWorkflowHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteComfyUiWorkflowController extends Controller
{
    public function __invoke(int $family, DeleteComfyUiWorkflowHandler $handler): Response
    {
        $handler->handle($family);

        return response()->noContent();
    }
}
