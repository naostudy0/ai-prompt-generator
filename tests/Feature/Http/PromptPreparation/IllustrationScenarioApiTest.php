<?php

namespace Tests\Feature\Http\PromptPreparation;

use App\Application\PromptPreparation\DTO\GeneratedScenarioPrompts;
use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\DTO\PlannedIllustrationScenario;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Ports\IllustrationScenarioPlanner;
use App\Application\PromptPreparation\Ports\ScenarioIllustrationPromptGenerator;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use Tests\TestCase;

final class IllustrationScenarioApiTest extends TestCase
{
    public function test_シナリオ案を生成してcamelCaseで返す(): void
    {
        $this->app->instance(IllustrationScenarioPlanner::class, new class implements IllustrationScenarioPlanner {
            public function plan(string $systemPrompt, string $description, int $sceneCount): PlannedIllustrationScenario
            {
                return new PlannedIllustrationScenario('同じ人物', [new IllustrationScenarioScene('scene-1', '出発', '家を出る', '')], 'model', 12);
            }
        });

        $this->postJson('/illustration-scenarios/plan', ['description' => '　登山　', 'sceneCount' => 3])
            ->assertOk()->assertJsonPath('continuityNotes', '同じ人物')->assertJsonPath('scenes.0.id', 'scene-1');
    }

    public function test_全場面を生成して入力順で返す(): void
    {
        $this->bindGenerator();
        $payload = $this->scenarioPayload();
        $this->postJson('/illustration-scenarios/prompts', $payload)
            ->assertOk()->assertJsonPath('scenes.1.id', 'b')->assertJsonPath('promptStyle', 'danbooru_tags');
    }

    public function test_対象場面だけを再生成する(): void
    {
        $this->bindGenerator();
        $payload = $this->scenarioPayload() + ['currentPrompts' => [
            ['id' => 'a', 'positivePrompt' => 'old a', 'negativePrompt' => ''],
            ['id' => 'b', 'positivePrompt' => 'old b', 'negativePrompt' => ''],
        ], 'targetId' => 'b', 'instruction' => '夕方'];
        $this->postJson('/illustration-scenarios/scenes/prompt', $payload)
            ->assertOk()->assertJsonPath('scene.id', 'b')->assertJsonPath('scene.positivePrompt', 'new b');
    }

    public function test_境界外の件数と重複IDと空白だけの必須文字列を拒否する(): void
    {
        $this->postJson('/illustration-scenarios/plan', ['description' => '　', 'sceneCount' => 2])->assertUnprocessable();
        $payload = $this->scenarioPayload();
        $payload['scenes'][1]['id'] = 'a';
        $this->postJson('/illustration-scenarios/prompts', $payload)->assertUnprocessable();
    }

    public function test_外部サービスの不正応答を502へ変換する(): void
    {
        $this->app->instance(IllustrationScenarioPlanner::class, new class implements IllustrationScenarioPlanner {
            public function plan(string $systemPrompt, string $description, int $sceneCount): PlannedIllustrationScenario
            {
                throw new InvalidIllustrationPromptResponse('raw upstream response');
            }
        });

        $this->postJson('/illustration-scenarios/plan', ['description' => '登山', 'sceneCount' => 5])
            ->assertStatus(502)->assertJsonMissing(['message' => 'raw upstream response']);
    }

    private function bindGenerator(): void
    {
        $this->app->instance(ScenarioIllustrationPromptGenerator::class, new class implements ScenarioIllustrationPromptGenerator {
            public function generateAll(string $systemPrompt, string $description, string $continuityNotes, array $scenes, PromptStyle $promptStyle): GeneratedScenarioPrompts
            {
                return new GeneratedScenarioPrompts([new ScenarioIllustrationPrompt('a', 'p a', ''), new ScenarioIllustrationPrompt('b', 'p b', '')], 'model', 20);
            }
            public function regenerateOne(string $systemPrompt, string $description, string $continuityNotes, array $scenes, array $currentPrompts, string $targetId, string $instruction, PromptStyle $promptStyle): ScenarioIllustrationPrompt
            {
                return new ScenarioIllustrationPrompt($targetId, 'new '.$targetId, 'negative');
            }
        });
    }

    private function scenarioPayload(): array
    {
        return ['description' => '登山', 'continuityNotes' => '同じ人物', 'promptStyle' => 'danbooru_tags', 'scenes' => [
            ['id' => 'a', 'title' => '出発', 'description' => '家を出る', 'changesFromPrevious' => ''],
            ['id' => 'b', 'title' => '登頂', 'description' => '頂上へ着く', 'changesFromPrevious' => '防寒着'],
            ['id' => 'c', 'title' => '景色', 'description' => '景色を見る', 'changesFromPrevious' => ''],
        ]];
    }
}
