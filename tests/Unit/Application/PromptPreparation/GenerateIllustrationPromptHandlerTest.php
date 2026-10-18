<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\DTO\GeneratedIllustrationPrompt;
use App\Application\PromptPreparation\DTO\ReusableIllustrationTagGroup;
use App\Application\PromptPreparation\Ports\IllustrationPromptGenerator;
use App\Application\PromptPreparation\Ports\IllustrationPromptInstructions;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\GenerateIllustrationPromptHandler;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\GenerateIllustrationPromptInput;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use PHPUnit\Framework\TestCase;

final class GenerateIllustrationPromptHandlerTest extends TestCase
{
    public function test_danbooru形式の指示を選び生成結果を返す(): void
    {
        $instructions = $this->instructionsFake([
            PromptStyle::DanbooruTags->value => 'danbooru instruction',
        ]);
        $generator = $this->generatorFake(new GeneratedIllustrationPrompt(
            positivePrompt: '1girl, blue eyes',
            negativePrompt: 'low quality',
            model: 'illustration-model',
            generationTimeMs: 321,
            reusableTagGroups: [
                new ReusableIllustrationTagGroup('黒髪ロングの女の子', '1girl, solo, long black hair'),
                new ReusableIllustrationTagGroup('夕暮れの教室', 'classroom, sunset'),
            ],
        ));

        $result = (new GenerateIllustrationPromptHandler($instructions, $generator))->handle(
            new GenerateIllustrationPromptInput('青い目の人物', PromptStyle::DanbooruTags),
        );

        self::assertSame('1girl, blue eyes', $result->positivePrompt);
        self::assertSame('low quality', $result->negativePrompt);
        self::assertSame('illustration-model', $result->model);
        self::assertSame('danbooru_tags', $result->promptStyle);
        self::assertSame(321, $result->generationTimeMs);
        self::assertSame('黒髪ロングの女の子', $result->reusableTagGroups[0]->label);
        self::assertSame('1girl, solo, long black hair', $result->reusableTagGroups[0]->tags);
        self::assertSame('夕暮れの教室', $result->reusableTagGroups[1]->label);
        self::assertSame('classroom, sunset', $result->reusableTagGroups[1]->tags);
    }

    public function test_natural_language形式の指示を選ぶ(): void
    {
        $instructions = $this->instructionsFake([
            PromptStyle::NaturalLanguage->value => 'natural language instruction',
        ]);
        $generator = $this->generatorFake(new GeneratedIllustrationPrompt(
            positivePrompt: 'A person with blue eyes.',
            negativePrompt: '',
            model: 'illustration-model',
            generationTimeMs: 45,
        ));

        $result = (new GenerateIllustrationPromptHandler($instructions, $generator))->handle(
            new GenerateIllustrationPromptInput('青い目の人物', PromptStyle::NaturalLanguage),
        );

        self::assertSame('natural_language', $result->promptStyle);
        self::assertSame('A person with blue eyes.', $result->positivePrompt);
    }

    public function test_説明とスタイル以外を外部生成ポートへ漏らさない(): void
    {
        $received = [];
        $instructions = $this->instructionsFake([
            PromptStyle::DanbooruTags->value => 'fixed system instruction',
        ]);
        $capture = static function (string $systemPrompt, string $description) use (&$received): void {
            $received = compact('systemPrompt', 'description');
        };
        $generator = new class ($capture) implements IllustrationPromptGenerator {
            public function __construct(private readonly \Closure $capture)
            {
            }

            public function generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt
            {
                ($this->capture)($systemPrompt, $description);

                return new GeneratedIllustrationPrompt('positive', 'negative', 'model', 10);
            }
        };

        (new GenerateIllustrationPromptHandler($instructions, $generator))->handle(
            new GenerateIllustrationPromptInput('explicit description', PromptStyle::DanbooruTags),
        );

        self::assertSame([
            'systemPrompt' => 'fixed system instruction',
            'description' => 'explicit description',
        ], $received);
    }

    /** @param array<string, string> $values */
    private function instructionsFake(array $values): IllustrationPromptInstructions
    {
        return new class ($values) implements IllustrationPromptInstructions {
            /** @param array<string, string> $values */
            public function __construct(private readonly array $values)
            {
            }

            public function for(PromptStyle $style): string
            {
                return $this->values[$style->value];
            }
        };
    }

    private function generatorFake(GeneratedIllustrationPrompt $generated): IllustrationPromptGenerator
    {
        return new class ($generated) implements IllustrationPromptGenerator {
            public function __construct(private readonly GeneratedIllustrationPrompt $generated)
            {
            }

            public function generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt
            {
                return $this->generated;
            }
        };
    }
}
