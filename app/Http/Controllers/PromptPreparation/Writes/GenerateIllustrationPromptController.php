<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\GenerateIllustrationPromptHandler;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\GenerateIllustrationPromptInput;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\IllustrationPromptTemplate;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\GenerateIllustrationPromptRequest;
use Illuminate\Http\JsonResponse;

final class GenerateIllustrationPromptController extends Controller
{
    public function __invoke(
        GenerateIllustrationPromptRequest $request,
        GenerateIllustrationPromptHandler $handler,
    ): JsonResponse {
        $style = PromptStyle::from((string) $request->input('promptStyle', PromptStyle::DanbooruTags->value));
        $description = (string) $request->validated('description', '');
        if ($request->validated('inputMode') === 'template') {
            /** @var array<string, string|null> $template */
            $template = $request->validated('template');
            $description = (new IllustrationPromptTemplate(
                character: (string) ($template['character'] ?? ''),
                action: (string) ($template['action'] ?? ''),
                background: (string) ($template['background'] ?? ''),
                composition: (string) ($template['composition'] ?? ''),
                atmosphere: (string) ($template['atmosphere'] ?? ''),
                constraints: (string) ($template['constraints'] ?? ''),
            ))->toDescription();
        }

        try {
            $result = $handler->handle(new GenerateIllustrationPromptInput(
                description: $description,
                promptStyle: $style,
            ));
        } catch (IllustrationPromptServiceUnavailable) {
            return response()->json(['message' => 'プロンプト生成サービスを現在利用できません。'], 503);
        } catch (InvalidIllustrationPromptResponse) {
            return response()->json(['message' => '生成サービスから不正な応答を受信しました。'], 502);
        }

        return response()->json([
            'positivePrompt' => $result->positivePrompt,
            'negativePrompt' => $result->negativePrompt,
            'model' => $result->model,
            'promptStyle' => $result->promptStyle,
            'generationTimeMs' => $result->generationTimeMs,
            'reusableTagGroups' => array_map(
                static fn ($group): array => [
                    'label' => $group->label,
                    'tags' => $group->tags,
                ],
                $result->reusableTagGroups,
            ),
        ]);
    }
}
