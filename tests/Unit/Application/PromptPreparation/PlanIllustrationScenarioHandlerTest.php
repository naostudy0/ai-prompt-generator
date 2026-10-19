<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\DTO\PlannedIllustrationScenario;
use App\Application\PromptPreparation\Ports\IllustrationScenarioInstructions;
use App\Application\PromptPreparation\Ports\IllustrationScenarioPlanner;
use App\Application\PromptPreparation\Writes\PlanIllustrationScenario\PlanIllustrationScenarioHandler;
use App\Application\PromptPreparation\Writes\PlanIllustrationScenario\PlanIllustrationScenarioInput;
use PHPUnit\Framework\TestCase;

final class PlanIllustrationScenarioHandlerTest extends TestCase
{
    public function test_説明と場面数を指示文とともにPlannerへ渡す(): void
    {
        $captured = (object) [];
        $instructions = new class implements IllustrationScenarioInstructions
        {
            public function forPlanning(): string
            {
                return 'scenario planning instructions';
            }
        };
        $planner = new class($captured) implements IllustrationScenarioPlanner
        {
            public function __construct(private readonly object $captured) {}

            public function plan(string $systemPrompt, string $description, int $sceneCount): PlannedIllustrationScenario
            {
                $this->captured->arguments = [$systemPrompt, $description, $sceneCount];

                return new PlannedIllustrationScenario('', [], 'model', 10);
            }
        };

        (new PlanIllustrationScenarioHandler($instructions, $planner))->handle(
            new PlanIllustrationScenarioInput('登山して山頂へ到達する', 5),
        );

        self::assertSame(
            ['scenario planning instructions', '登山して山頂へ到達する', 5],
            $captured->arguments,
        );
    }

    public function test_Plannerのシナリオ案と計測値をResultへ変換する(): void
    {
        $scenes = [
            new IllustrationScenarioScene('scene-b', '登山口', '登山口へ着く', ''),
            new IllustrationScenarioScene('scene-a', '山頂', '山頂へ着く', '防寒着を追加する'),
        ];
        $instructions = new class implements IllustrationScenarioInstructions
        {
            public function forPlanning(): string
            {
                return 'instructions';
            }
        };
        $planner = new class($scenes) implements IllustrationScenarioPlanner
        {
            /** @param list<IllustrationScenarioScene> $scenes */
            public function __construct(private readonly array $scenes) {}

            public function plan(string $systemPrompt, string $description, int $sceneCount): PlannedIllustrationScenario
            {
                return new PlannedIllustrationScenario(
                    '同じ人物と赤い登山服を維持する',
                    $this->scenes,
                    'scenario-model',
                    3210,
                );
            }
        };

        $result = (new PlanIllustrationScenarioHandler($instructions, $planner))->handle(
            new PlanIllustrationScenarioInput('登山', 5),
        );

        self::assertSame('同じ人物と赤い登山服を維持する', $result->continuityNotes);
        self::assertSame(['scene-b', 'scene-a'], array_map(
            static fn (IllustrationScenarioScene $scene): string => $scene->id,
            $result->scenes,
        ));
        self::assertSame('scenario-model', $result->model);
        self::assertSame(3210, $result->generationTimeMs);
    }
}
