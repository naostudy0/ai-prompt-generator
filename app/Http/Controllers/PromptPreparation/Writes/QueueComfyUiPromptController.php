<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Exceptions\ComfyUiQueueFailed;
use App\Application\PromptPreparation\Exceptions\ComfyUiWorkflowNotConfigured;
use App\Application\PromptPreparation\Exceptions\InvalidComfyUiWorkflow;
use App\Application\PromptPreparation\Writes\QueueComfyUiPrompt\QueueComfyUiPromptHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\QueueComfyUiPromptRequest;
use Illuminate\Http\JsonResponse;

final class QueueComfyUiPromptController extends Controller
{
    public function __invoke(QueueComfyUiPromptRequest $request, QueueComfyUiPromptHandler $handler): JsonResponse
    {
        try {
            $result = $handler->handle(
                $request->integer('modelFamilyId'),
                (string) $request->input('positive', ''),
                (string) $request->input('negative', ''),
            );
        } catch (ComfyUiWorkflowNotConfigured|InvalidComfyUiWorkflow $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (ComfyUiQueueFailed $exception) {
            return response()->json(['message' => $exception->getMessage(), 'resultUnknown' => $exception->resultUnknown], $exception->resultUnknown ? 504 : 503);
        }

        return response()->json($result, 202);
    }
}
