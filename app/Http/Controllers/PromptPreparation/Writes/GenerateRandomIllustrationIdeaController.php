<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\DTO\RandomIllustrationSelectionIds;
use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Exceptions\InvalidRandomIllustrationSelection;
use App\Application\PromptPreparation\Writes\GenerateRandomIllustrationIdea\GenerateRandomIllustrationIdeaHandler;
use App\Application\PromptPreparation\Writes\GenerateRandomIllustrationIdea\GenerateRandomIllustrationIdeaInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\GenerateRandomIllustrationIdeaRequest;
use Illuminate\Http\JsonResponse;

final class GenerateRandomIllustrationIdeaController extends Controller
{
    public function __invoke(GenerateRandomIllustrationIdeaRequest $request, GenerateRandomIllustrationIdeaHandler $handler): JsonResponse
    {
        try {
            $result = $handler->handle(new GenerateRandomIllustrationIdeaInput($this->selectionIds($request->validated('fixedSelections'))));
        } catch (InvalidRandomIllustrationSelection $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (IllustrationPromptServiceUnavailable) {
            return response()->json(['message' => 'AIサービスを利用できません。時間をおいて再試行してください。'], 503);
        } catch (InvalidIllustrationPromptResponse) {
            return response()->json(['message' => 'AIサービスから正しい形式の回答を取得できませんでした。'], 502);
        }
        return response()->json(['ideaId' => $result->ideaId, 'description' => $result->description, 'fixedSelectionSummaries' => $result->fixedSelectionSummaries, 'locationAnchor' => $result->locationAnchor, 'model' => $result->model, 'generationTimeMs' => $result->generationTimeMs]);
    }

    /** @param array<string,mixed> $value */
    public function selectionIds(array $value): RandomIllustrationSelectionIds
    {
        return new RandomIllustrationSelectionIds((bool) $value['positiveDefault'], (bool) $value['negativeDefault'], (int) $value['modelFamilyId'], $value['character']['loraId'], (float) $value['character']['strength'], $value['character']['triggerId'], $value['character']['outfitId'], $value['clothingLoras'], $value['optionIds']);
    }
}
