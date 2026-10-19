<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\DTO\GeneratedScenarioPrompts;
use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Ports\IllustrationScenarioInstructions;
use App\Application\PromptPreparation\Ports\ScenarioIllustrationPromptGenerator;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Application\PromptPreparation\Writes\GenerateScenarioPrompts\GenerateScenarioPromptsHandler;
use App\Application\PromptPreparation\Writes\GenerateScenarioPrompts\GenerateScenarioPromptsInput;
use PHPUnit\Framework\TestCase;

final class GenerateScenarioPromptsHandlerTest extends TestCase
{
    public function test_編集済みの全場面を順序どおり生成して結果を返す(): void
    {
        $scenes = [new IllustrationScenarioScene('b', '中盤', '登る', ''), new IllustrationScenarioScene('a', '頂上', '着く', '防寒着')];
        $captured = (object) [];
        $instructions = new class implements IllustrationScenarioInstructions {
            public function forPlanning(): string { return 'plan'; }
            public function forPromptStyle(PromptStyle $style): string { return 'all-'.$style->value; }
            public function forSceneRegeneration(PromptStyle $style): string { return 'one-'.$style->value; }
        };
        $generator = new class($captured) implements ScenarioIllustrationPromptGenerator {
            public function __construct(private readonly object $captured) {}
            public function generateAll(string $systemPrompt, string $description, string $continuityNotes, array $scenes, PromptStyle $promptStyle): GeneratedScenarioPrompts
            {
                $this->captured->values = [$systemPrompt, $description, $continuityNotes, $scenes, $promptStyle];
                return new GeneratedScenarioPrompts([new ScenarioIllustrationPrompt('b', 'p1', 'n1'), new ScenarioIllustrationPrompt('a', 'p2', 'n2')], 'model', 20);
            }
            public function regenerateOne(string $systemPrompt, string $description, string $continuityNotes, array $scenes, array $currentPrompts, string $targetId, string $instruction, PromptStyle $promptStyle): ScenarioIllustrationPrompt { throw new \LogicException; }
        };

        $result = (new GenerateScenarioPromptsHandler($instructions, $generator))->handle(new GenerateScenarioPromptsInput('登山', '同じ人物', $scenes, PromptStyle::DanbooruTags));

        self::assertSame(['all-danbooru_tags', '登山', '同じ人物'], array_slice($captured->values, 0, 3));
        self::assertSame(['b', 'a'], array_map(static fn ($scene): string => $scene->id, $captured->values[3]));
        self::assertSame(['b', 'a'], array_map(static fn ($scene): string => $scene->id, $result->scenes));
        self::assertSame('model', $result->model);
        self::assertSame(20, $result->generationTimeMs);
    }
}
