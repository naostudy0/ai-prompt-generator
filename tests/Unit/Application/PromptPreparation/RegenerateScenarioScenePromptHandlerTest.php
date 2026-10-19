<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\DTO\GeneratedScenarioPrompts;
use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Ports\IllustrationScenarioInstructions;
use App\Application\PromptPreparation\Ports\ScenarioIllustrationPromptGenerator;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt\RegenerateScenarioScenePromptHandler;
use App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt\RegenerateScenarioScenePromptInput;
use PHPUnit\Framework\TestCase;

final class RegenerateScenarioScenePromptHandlerTest extends TestCase
{
    public function test_全体文脈を渡して対象場面だけを再生成する(): void
    {
        $scenes = [new IllustrationScenarioScene('a', '出発', '出る', ''), new IllustrationScenarioScene('b', '頂上', '着く', '')];
        $prompts = [new ScenarioIllustrationPrompt('a', 'old-a', ''), new ScenarioIllustrationPrompt('b', 'old-b', '')];
        $captured = (object) [];
        $instructions = new class implements IllustrationScenarioInstructions {
            public function forPlanning(): string { return 'plan'; }
            public function forPromptStyle(PromptStyle $style): string { return 'all'; }
            public function forSceneRegeneration(PromptStyle $style): string { return 'one-'.$style->value; }
        };
        $generator = new class($captured) implements ScenarioIllustrationPromptGenerator {
            public function __construct(private readonly object $captured) {}
            public function generateAll(string $systemPrompt, string $description, string $continuityNotes, array $scenes, PromptStyle $promptStyle): GeneratedScenarioPrompts { throw new \LogicException; }
            public function regenerateOne(string $systemPrompt, string $description, string $continuityNotes, array $scenes, array $currentPrompts, string $targetId, string $instruction, PromptStyle $promptStyle): ScenarioIllustrationPrompt
            {
                $this->captured->values = func_get_args();
                return new ScenarioIllustrationPrompt('b', 'new-b', 'negative');
            }
        };

        $result = (new RegenerateScenarioScenePromptHandler($instructions, $generator))->handle(new RegenerateScenarioScenePromptInput('登山', '同じ人物', $scenes, $prompts, 'b', '夕方にする', PromptStyle::NaturalLanguage));

        self::assertSame('one-natural_language', $captured->values[0]);
        self::assertSame($scenes, $captured->values[3]);
        self::assertSame($prompts, $captured->values[4]);
        self::assertSame(['b', '夕方にする', PromptStyle::NaturalLanguage], array_slice($captured->values, 5));
        self::assertSame('b', $result->scene->id);
        self::assertSame('new-b', $result->scene->positivePrompt);
    }
}
