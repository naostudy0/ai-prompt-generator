<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Writes\PlanIllustrationScenario\PlanIllustrationScenarioHandler;
use App\Application\PromptPreparation\Writes\PlanIllustrationScenario\PlanIllustrationScenarioInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\PlanIllustrationScenarioRequest;
use Illuminate\Http\JsonResponse;

final class PlanIllustrationScenarioController extends Controller
{
    public function __invoke(PlanIllustrationScenarioRequest $request, PlanIllustrationScenarioHandler $handler): JsonResponse
    {
        try {
            $result = $handler->handle(new PlanIllustrationScenarioInput((string) $request->validated('description'), (int) $request->validated('sceneCount')));
        } catch (IllustrationPromptServiceUnavailable) {
            return response()->json(['message' => 'シナリオ生成サービスを現在利用できません。'], 503);
        } catch (InvalidIllustrationPromptResponse $exception) {
            $message = match ($exception->reason) {
                'invalid_json' => '生成結果をJSONとして読み取れませんでした。もう一度お試しください。',
                'scene_count_mismatch' => '生成された場面数が指定と一致しませんでした。もう一度お試しください。',
                'required_fields_missing' => '生成結果に必要な項目が不足していました。もう一度お試しください。',
                default => '生成サービスから不正な応答を受信しました。',
            };

            return response()->json(['message' => $message], 502);
        }
        return response()->json(['continuityNotes' => $result->continuityNotes, 'scenes' => array_map(static fn ($scene): array => ['id' => $scene->id, 'title' => $scene->title, 'description' => $scene->description, 'changesFromPrevious' => $scene->changesFromPrevious], $result->scenes), 'model' => $result->model, 'generationTimeMs' => $result->generationTimeMs]);
    }
}
