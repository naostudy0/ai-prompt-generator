<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetComfyUiWorkflow\GetComfyUiWorkflowHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetComfyUiWorkflowController extends Controller
{
    public function __invoke(int $family, GetComfyUiWorkflowHandler $handler): JsonResponse
    {
        return response()->json($handler->handle($family));
    }
}
