<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Exceptions\InvalidComfyUiImage;
use App\Application\PromptPreparation\Exceptions\UnsupportedComfyUiWorkflow;
use App\Application\PromptPreparation\Queries\AnalyzeComfyUiImage\AnalyzeComfyUiImageHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\AnalyzeComfyUiImageRequest;
use Illuminate\Http\JsonResponse;

final class AnalyzeComfyUiImageController extends Controller
{
    public function __invoke(AnalyzeComfyUiImageRequest $request, AnalyzeComfyUiImageHandler $handler): JsonResponse
    {
        $path = $request->file('image')?->getRealPath();
        if (! is_string($path)) {
            return response()->json(['message' => '画像を読み取れませんでした。'], 422);
        }
        try {
            return response()->json($handler->handle($path));
        } catch (InvalidComfyUiImage|UnsupportedComfyUiWorkflow $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
