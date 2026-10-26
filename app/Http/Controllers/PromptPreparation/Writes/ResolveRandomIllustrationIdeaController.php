<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Exceptions\InvalidRandomIllustrationSelection;
use App\Application\PromptPreparation\Exceptions\RandomIllustrationContextTooLarge;
use App\Application\PromptPreparation\Writes\ResolveRandomIllustrationIdea\ResolveRandomIllustrationIdeaHandler;
use App\Application\PromptPreparation\Writes\ResolveRandomIllustrationIdea\ResolveRandomIllustrationIdeaInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\ResolveRandomIllustrationIdeaRequest;
use Illuminate\Http\JsonResponse;

final class ResolveRandomIllustrationIdeaController extends Controller
{
    public function __invoke(ResolveRandomIllustrationIdeaRequest $request, ResolveRandomIllustrationIdeaHandler $handler, GenerateRandomIllustrationIdeaController $mapper): JsonResponse
    {
        $validated = $request->validated();
        try {
            $result = $handler->handle(new ResolveRandomIllustrationIdeaInput($validated['ideaId'], $validated['description'], $mapper->selectionIds($validated['fixedSelections'])));
        } catch (InvalidRandomIllustrationSelection|RandomIllustrationContextTooLarge $exception) {
            return response()->json(['message' => $exception->getMessage().' 候補を整理して再試行してください。'], 422);
        } catch (IllustrationPromptServiceUnavailable) {
            return response()->json(['message' => 'AIサービスを利用できません。時間をおいて再試行してください。'], 503);
        } catch (InvalidIllustrationPromptResponse) {
            return response()->json(['message' => 'AIサービスから正しい形式の回答を取得できませんでした。'], 502);
        }
        return response()->json(['ideaId' => $result->ideaId, 'resolvedDescription' => $result->resolvedDescription, 'optionIds' => $result->optionIds, 'suggestions' => array_map(static fn ($suggestion): array => $suggestion->toArray(), $result->suggestions), 'adjustments' => $result->adjustments, 'model' => $result->model, 'generationTimeMs' => $result->generationTimeMs]);
    }
}
