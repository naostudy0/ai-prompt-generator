<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Infrastructure\PromptPreparation\External\IllustrationLlm\FileIllustrationPromptInstructions;
use App\Infrastructure\PromptPreparation\External\IllustrationLlm\FileIllustrationScenarioInstructions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MultipleCharacterIllustrationInstructionsTest extends TestCase
{
    #[DataProvider('multipleCharacterInstructions')]
    public function test_画像プロンプト生成用の指示は複数人物を順序どおり人物別セクションへ分離するよう求める(
        callable $loadInstruction,
        string $styleRequirement,
        string $jsonRequirement,
    ): void {
        $instruction = $loadInstruction();

        $scenePosition = strpos($instruction, '[Scene]');
        $characterAPosition = strpos($instruction, '[Character A]');
        $characterBPosition = strpos($instruction, '[Character B]');
        $interactionsPosition = strpos($instruction, '[Interactions]');

        self::assertIsInt($scenePosition);
        self::assertIsInt($characterAPosition);
        self::assertIsInt($characterBPosition);
        self::assertIsInt($interactionsPosition);
        self::assertLessThan($characterAPosition, $scenePosition);
        self::assertLessThan($characterBPosition, $characterAPosition);
        self::assertLessThan($interactionsPosition, $characterBPosition);
        self::assertStringContainsString('one character section for each person', $instruction);
        self::assertStringContainsString('subject and target', $instruction);
        self::assertStringContainsString('exact number of people and specified gender counts', $instruction);
        self::assertStringContainsString('appearance, clothing, position, expression, or action with another character', $instruction);
        self::assertStringContainsString('positive_prompt in English', $instruction);
        self::assertStringContainsString($styleRequirement, $instruction);
        self::assertStringContainsString($jsonRequirement, $instruction);
        self::assertStringContainsString('one person', $instruction);
        self::assertStringContainsString('existing unsectioned format', $instruction);
    }

    public function test_シナリオ案生成用の指示には画像positive用の複数人物規則を含めない(): void
    {
        $instruction = (new FileIllustrationScenarioInstructions())->forPlanning();

        self::assertStringNotContainsString('[Scene]', $instruction);
        self::assertStringNotContainsString('[Character A]', $instruction);
        self::assertStringNotContainsString('[Interactions]', $instruction);
    }

    /** @return array<string, array{callable(): string, string, string}> */
    public static function multipleCharacterInstructions(): array
    {
        return [
            'single danbooru generation' => [
                static fn (): string => (new FileIllustrationPromptInstructions())->for(PromptStyle::DanbooruTags),
                'comma-separated English tags for Danbooru style',
                'Return only one valid JSON object with exactly these keys',
            ],
            'single natural-language generation' => [
                static fn (): string => (new FileIllustrationPromptInstructions())->for(PromptStyle::NaturalLanguage),
                'concise English sentences for natural-language style',
                'Return only one valid JSON object with exactly these keys',
            ],
            'scenario danbooru generation' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forPromptStyle(PromptStyle::DanbooruTags),
                'comma-separated English tags for Danbooru style',
                'Return only JSON',
            ],
            'scenario natural-language generation' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forPromptStyle(PromptStyle::NaturalLanguage),
                'concise English sentences for natural-language style',
                'Return only JSON',
            ],
            'scenario danbooru regeneration' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forSceneRegeneration(PromptStyle::DanbooruTags),
                'comma-separated English tags for Danbooru style',
                'Return only JSON',
            ],
            'scenario natural-language regeneration' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forSceneRegeneration(PromptStyle::NaturalLanguage),
                'concise English sentences for natural-language style',
                'Return only JSON',
            ],
        ];
    }
}
