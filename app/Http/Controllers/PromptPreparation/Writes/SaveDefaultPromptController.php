<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveDefaultPrompt\SaveDefaultPromptHandler;
use App\Application\PromptPreparation\Writes\SaveDefaultPrompt\SaveDefaultPromptInput;
use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveDefaultPromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveDefaultPromptController extends Controller
{
    public function __invoke(
        SaveDefaultPromptRequest $request,
        string $polarity,
        SaveDefaultPromptHandler $handler,
    ): JsonResponse {
        $promptPolarity = PromptPolarity::tryFrom($polarity);

        abort_if($promptPolarity === null, 404);

        $result = $handler->handle(new SaveDefaultPromptInput(
            polarity: $promptPolarity,
            content: $request->content(),
        ));

        return response()->json([
            'polarity' => $result->polarity->value,
            'content' => $result->content,
            'formatSucceeded' => $result->formatSucceeded,
        ]);
    }
}
