<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Exceptions\InvalidComfyUiWorkflow;
use App\Application\PromptPreparation\Writes\SaveComfyUiWorkflow\SaveComfyUiWorkflowHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveComfyUiWorkflowRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class SaveComfyUiWorkflowController extends Controller
{
    public function __invoke(int $family, SaveComfyUiWorkflowRequest $request, SaveComfyUiWorkflowHandler $handler): JsonResponse
    {
        $file = $request->file('workflow');
        if ($file === null) {
            throw ValidationException::withMessages(['workflow' => ['JSONファイルを選択してください。']]);
        }
        $content = $file->get();
        if (!is_string($content)) {
            throw ValidationException::withMessages(['workflow' => ['JSONファイルを読み取れません。']]);
        }
        $mappings = [
            'positive' => ['nodeId' => $request->string('positiveNodeId')->toString(), 'inputName' => $request->string('positiveInputName')->toString()],
            'negative' => ['nodeId' => $request->string('negativeNodeId')->toString(), 'inputName' => $request->string('negativeInputName')->toString()],
            'seed' => ['nodeId' => $request->string('seedNodeId')->toString(), 'inputName' => $request->string('seedInputName')->toString()],
        ];
        try {
            $handler->handle($family, $file->getClientOriginalName(), $content, $mappings);
        } catch (InvalidComfyUiWorkflow $exception) {
            throw ValidationException::withMessages(['workflow' => [$exception->getMessage()]]);
        }

        return response()->json(['saved' => true]);
    }
}
