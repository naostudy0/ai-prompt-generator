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

        self::assertStringContainsString('natural Japanese', $instruction);
        self::assertStringContainsString('grammatically correct person names', $instruction);
        self::assertStringNotContainsString('[Scene]', $instruction);
        self::assertStringNotContainsString('[Character A]', $instruction);
        self::assertStringNotContainsString('[Interactions]', $instruction);
    }

    #[DataProvider('scenarioPromptInstructions')]
    public function test_シナリオ画像プロンプトの指示は形式に応じた英語出力を求める(
        callable $loadInstruction,
        string $format,
    ): void {
        $instruction = $loadInstruction();

        self::assertStringContainsString('positive_prompt and negative_prompt in English only', $instruction);
        self::assertStringContainsString($format, $instruction);
        self::assertStringContainsString('Do not return Japanese scene descriptions as prompts', $instruction);
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

    /** @return array<string, array{callable(): string, string}> */
    public static function scenarioPromptInstructions(): array
    {
        return [
            'all scenes in Danbooru style' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forPromptStyle(PromptStyle::DanbooruTags),
                'comma-separated Danbooru-style tags',
            ],
            'all scenes in natural-language style' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forPromptStyle(PromptStyle::NaturalLanguage),
                'concise image-generation sentences',
            ],
            'one scene in Danbooru style' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forSceneRegeneration(PromptStyle::DanbooruTags),
                'comma-separated Danbooru-style tags',
            ],
            'one scene in natural-language style' => [
                static fn (): string => (new FileIllustrationScenarioInstructions())->forSceneRegeneration(PromptStyle::NaturalLanguage),
                'concise image-generation sentences',
            ],
        ];
    }

}
