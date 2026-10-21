<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Infrastructure\PromptPreparation\External\IllustrationLlm\OllamaIllustrationScenarioGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OllamaIllustrationScenarioGeneratorTest extends TestCase
{
    public function test_指定件数の構造化シナリオ案を生成する(): void
    {
        config(['services.illustration_llm.model' => 'gemma3:12b']);
        Http::fake(['*' => Http::response(['model' => 'test-model', 'message' => ['content' => json_encode([
            'continuity_notes' => '同じ人物',
            'scenes' => [
                ['id' => 'scene-1', 'title' => '出発', 'description' => '家を出る', 'changes_from_previous' => ''],
                ['id' => 'scene-2', 'title' => '到着', 'description' => '登山口へ着く', 'changes_from_previous' => '場所を変更'],
                ['id' => 'scene-3', 'title' => '登頂', 'description' => '山頂へ着く', 'changes_from_previous' => '防寒着を追加'],
            ],
        ], JSON_THROW_ON_ERROR)]], 200)]);

        $result = app(OllamaIllustrationScenarioGenerator::class)->plan('planning system', '登山', 3);

        self::assertSame('同じ人物', $result->continuityNotes);
        self::assertSame(['scene-1', 'scene-2', 'scene-3'], array_map(static fn ($scene): string => $scene->id, $result->scenes));
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            return $data['model'] === 'gemma3:12b'
                && $data['stream'] === false
                && $data['think'] === false
                && $data['messages'][0]['content'] === 'planning system'
                && $data['format']['properties']['scenes']['minItems'] === 3
                && $data['format']['properties']['scenes']['maxItems'] === 3;
        });
    }

    public function test_シナリオ案の初回応答が不正JSONなら一度だけ再生成して正常応答を採用する(): void
    {
        Http::fakeSequence()
            ->push(['message' => ['content' => '{invalid']], 200)
            ->push(['model' => 'retry-model', 'message' => ['content' => json_encode([
                'continuity_notes' => '同じ二人',
                'scenes' => [
                    ['id' => 'scene-1', 'title' => '出発', 'description' => '森を出る', 'changes_from_previous' => ''],
                    ['id' => 'scene-2', 'title' => '道中', 'description' => '丘を登る', 'changes_from_previous' => '場所を変更'],
                    ['id' => 'scene-3', 'title' => '到着', 'description' => '頂上へ着く', 'changes_from_previous' => ''],
                ],
            ], JSON_THROW_ON_ERROR)]], 200);

        $result = app(OllamaIllustrationScenarioGenerator::class)->plan('planning system', '架空の冒険', 3);

        self::assertSame('retry-model', $result->model);
        self::assertSame('同じ二人', $result->continuityNotes);
        Http::assertSentCount(2);
    }

    public function test_シナリオ案が二回とも指定件数を満たさない場合は件数不一致として拒否する(): void
    {
        $invalidPlan = ['message' => ['content' => json_encode([
            'continuity_notes' => '同じ人物',
            'scenes' => [
                ['id' => 'scene-1', 'title' => '出発', 'description' => '家を出る', 'changes_from_previous' => ''],
            ],
        ], JSON_THROW_ON_ERROR)]];
        Http::fakeSequence()->push($invalidPlan, 200)->push($invalidPlan, 200);

        try {
            app(OllamaIllustrationScenarioGenerator::class)->plan('planning system', '架空の登山', 3);
            self::fail('Exception was not thrown.');
        } catch (InvalidIllustrationPromptResponse $exception) {
            self::assertSame('scene_count_mismatch', $exception->reason);
        }

        Http::assertSentCount(2);
    }

    public function test_全場面の順序とIDが一致する候補だけを受け付ける(): void
    {
        Http::fake(['*' => Http::response(['message' => ['content' => json_encode(['scenes' => [
            ['id' => 'a', 'positive_prompt' => 'positive a', 'negative_prompt' => 'negative a'],
            ['id' => 'b', 'positive_prompt' => 'positive b', 'negative_prompt' => ''],
        ]], JSON_THROW_ON_ERROR)]], 200)]);
        $scenes = [new IllustrationScenarioScene('a', 'A', 'scene A', ''), new IllustrationScenarioScene('b', 'B', 'scene B', 'change')];

        $result = app(OllamaIllustrationScenarioGenerator::class)->generateAll('system', 'description', 'continuity', $scenes, PromptStyle::DanbooruTags);

        self::assertSame(['a', 'b'], array_map(static fn ($scene): string => $scene->id, $result->scenes));
    }

    public function test_順序が異なる全場面応答を拒否する(): void
    {
        Http::fake(['*' => Http::response(['message' => ['content' => json_encode(['scenes' => [
            ['id' => 'b', 'positive_prompt' => 'positive b', 'negative_prompt' => ''],
            ['id' => 'a', 'positive_prompt' => 'positive a', 'negative_prompt' => ''],
        ]], JSON_THROW_ON_ERROR)]], 200)]);
        $scenes = [new IllustrationScenarioScene('a', 'A', 'scene A', ''), new IllustrationScenarioScene('b', 'B', 'scene B', '')];

        $this->expectException(InvalidIllustrationPromptResponse::class);
        app(OllamaIllustrationScenarioGenerator::class)->generateAll('system', 'description', '', $scenes, PromptStyle::DanbooruTags);
    }

    public function test_対象IDと一致する一場面だけを再生成する(): void
    {
        Http::fake(['*' => Http::response(['message' => ['content' => json_encode([
            'id' => 'b', 'positive_prompt' => 'new positive', 'negative_prompt' => 'new negative',
        ], JSON_THROW_ON_ERROR)]], 200)]);
        $scenes = [new IllustrationScenarioScene('a', 'A', 'scene A', ''), new IllustrationScenarioScene('b', 'B', 'scene B', '')];
        $current = [new ScenarioIllustrationPrompt('a', 'a', ''), new ScenarioIllustrationPrompt('b', 'b', '')];

        $result = app(OllamaIllustrationScenarioGenerator::class)->regenerateOne('system', 'description', 'continuity', $scenes, $current, 'b', '夕方', PromptStyle::NaturalLanguage);

        self::assertSame('b', $result->id);
        self::assertSame('new positive', $result->positivePrompt);
    }
}
