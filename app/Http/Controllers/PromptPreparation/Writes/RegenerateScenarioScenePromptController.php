<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt\RegenerateScenarioScenePromptHandler;
use App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt\RegenerateScenarioScenePromptInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\RegenerateScenarioScenePromptRequest;
use Illuminate\Http\JsonResponse;

final class RegenerateScenarioScenePromptController extends Controller
{
    public function __invoke(RegenerateScenarioScenePromptRequest $request, RegenerateScenarioScenePromptHandler $handler): JsonResponse
    {
        $style = PromptStyle::from((string) $request->input('promptStyle', PromptStyle::DanbooruTags->value));
        /** @var list<array{id: string, title: string, description: string, changesFromPrevious: string|null}> $validatedScenes */
        $validatedScenes = array_values($request->validated('scenes'));
        /** @var list<array{id: string, positivePrompt: string, negativePrompt: string|null}> $validatedPrompts */
        $validatedPrompts = array_values($request->validated('currentPrompts'));
        $scenes = array_map(static fn (array $scene): IllustrationScenarioScene => new IllustrationScenarioScene($scene['id'], $scene['title'], $scene['description'], (string) $scene['changesFromPrevious']), $validatedScenes);
        $prompts = array_map(static fn (array $scene): ScenarioIllustrationPrompt => new ScenarioIllustrationPrompt($scene['id'], $scene['positivePrompt'], (string) $scene['negativePrompt']), $validatedPrompts);
        try {
            $result = $handler->handle(new RegenerateScenarioScenePromptInput((string) $request->validated('description'), (string) $request->validated('continuityNotes'), $scenes, $prompts, (string) $request->validated('targetId'), (string) $request->validated('instruction'), $style));
        } catch (IllustrationPromptServiceUnavailable) {
            return response()->json(['message' => 'シナリオ生成サービスを現在利用できません。'], 503);
        } catch (InvalidIllustrationPromptResponse) {
            return response()->json(['message' => '生成サービスから不正な応答を受信しました。'], 502);
        }
        return response()->json(['scene' => ['id' => $result->scene->id, 'positivePrompt' => $result->scene->positivePrompt, 'negativePrompt' => $result->scene->negativePrompt], 'promptStyle' => $result->promptStyle]);
    }
}
