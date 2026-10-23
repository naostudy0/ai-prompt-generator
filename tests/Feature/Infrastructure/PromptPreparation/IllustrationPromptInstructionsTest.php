<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use App\Infrastructure\PromptPreparation\External\IllustrationLlm\FileIllustrationPromptInstructions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class IllustrationPromptInstructionsTest extends TestCase
{
    #[DataProvider('promptInstructions')]
    public function test_単一生成用の指示はテンプレートと自由入力の明示条件を優先する(PromptStyle $style): void
    {
        $instruction = (new FileIllustrationPromptInstructions())->for($style);

        foreach ([
            '【人物】',
            '【動作・表情】',
            '【場所・背景】',
            '【構図】',
            '【雰囲気】',
            '【必須条件】',
        ] as $heading) {
            self::assertStringContainsString($heading, $instruction);
        }
        self::assertStringContainsString('explicit user details take priority', $instruction);
        self::assertStringContainsString('mandatory constraints', $instruction);
        self::assertStringContainsString('only unspecified details', $instruction);
        self::assertStringContainsString('free-form input', $instruction);
    }

    /** @return array<string, array{PromptStyle}> */
    public static function promptInstructions(): array
    {
        return [
            'danbooru' => [PromptStyle::DanbooruTags],
            'natural language' => [PromptStyle::NaturalLanguage],
        ];
    }
}
