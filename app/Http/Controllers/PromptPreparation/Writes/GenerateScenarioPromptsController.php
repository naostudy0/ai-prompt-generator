<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Application\PromptPreparation\Writes\GenerateScenarioPrompts\GenerateScenarioPromptsHandler;
use App\Application\PromptPreparation\Writes\GenerateScenarioPrompts\GenerateScenarioPromptsInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\GenerateScenarioPromptsRequest;
use Illuminate\Http\JsonResponse;

final class GenerateScenarioPromptsController extends Controller
{
    public function __invoke(GenerateScenarioPromptsRequest $request, GenerateScenarioPromptsHandler $handler): JsonResponse
    {
        $style = PromptStyle::from((string) $request->input('promptStyle', PromptStyle::DanbooruTags->value));
        /** @var list<array{id: string, title: string, description: string, changesFromPrevious: string|null}> $validatedScenes */
        $validatedScenes = array_values($request->validated('scenes'));
        $scenes = array_map(static fn (array $scene): IllustrationScenarioScene => new IllustrationScenarioScene($scene['id'], $scene['title'], $scene['description'], (string) $scene['changesFromPrevious']), $validatedScenes);
        try {
            $result = $handler->handle(new GenerateScenarioPromptsInput((string) $request->validated('description'), (string) $request->validated('continuityNotes'), $scenes, $style));
        } catch (IllustrationPromptServiceUnavailable) {
            return response()->json(['message' => 'シナリオ生成サービスを現在利用できません。'], 503);
        } catch (InvalidIllustrationPromptResponse) {
            return response()->json(['message' => '生成サービスから不正な応答を受信しました。'], 502);
        }
        return response()->json(['scenes' => array_map(static fn ($scene): array => ['id' => $scene->id, 'positivePrompt' => $scene->positivePrompt, 'negativePrompt' => $scene->negativePrompt], $result->scenes), 'model' => $result->model, 'promptStyle' => $result->promptStyle, 'generationTimeMs' => $result->generationTimeMs]);
    }
}
