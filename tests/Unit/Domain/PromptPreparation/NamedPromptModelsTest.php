<?php

namespace Tests\Unit\Domain\PromptPreparation;

use App\Domain\PromptPreparation\Models\ActionPrompt;
use App\Domain\PromptPreparation\Models\CompositionPrompt;
use App\Domain\PromptPreparation\Models\ExpressionPrompt;
use App\Domain\PromptPreparation\Models\GazePrompt;
use App\Domain\PromptPreparation\Models\LocationPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Models\OptionPrompt;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NamedPromptModelsTest extends TestCase
{
    #[DataProvider('modelClasses')]
    public function test_登録名の前後空白を除いて候補を作成する(string $modelClass): void
    {
        /** @var ActionPrompt|CompositionPrompt|ExpressionPrompt|GazePrompt|LocationPrompt|OptionPrompt $model */
        $model = new $modelClass(12, '  登録名  ', PromptText::fromInput('prompt'));

        self::assertSame(12, $model->id);
        self::assertSame('登録名', $model->name);
        self::assertSame('prompt,', $model->content->value);
    }

    #[DataProvider('modelClasses')]
    public function test_空の登録名では候補を作成できない(string $modelClass): void
    {
        $this->expectException(InvalidArgumentException::class);

        new $modelClass(null, '  ', PromptText::fromInput('prompt'));
    }

    #[DataProvider('modelClasses')]
    public function test_空の文面では候補を作成できない(string $modelClass): void
    {
        $this->expectException(InvalidArgumentException::class);

        new $modelClass(null, '登録名', PromptText::fromInput(''));
    }

    /** @return iterable<string, array{class-string}> */
    public static function modelClasses(): iterable
    {
        yield '表情' => [ExpressionPrompt::class];
        yield '視線' => [GazePrompt::class];
        yield '場所' => [LocationPrompt::class];
        yield '構図' => [CompositionPrompt::class];
        yield '動作' => [ActionPrompt::class];
        yield 'オプション' => [OptionPrompt::class];
    }
}
