<?php

namespace Tests\Unit\Domain\PromptPreparation;

use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use App\Domain\PromptPreparation\Models\OptionSelectionMode;
use App\Domain\PromptPreparation\Models\PromptText;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NamedPromptModelsTest extends TestCase
{
    public function test_登録名の前後空白を除いてオプションを作成する(): void
    {
        $model = new OptionPrompt(12, 1, '  登録名  ', PromptText::fromInput('prompt'), 1);

        self::assertSame('登録名', $model->name);
        self::assertSame('prompt,', $model->content->value);
    }

    public function test_空の登録名ではオプションを作成できない(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OptionPrompt(null, 1, '  ', PromptText::fromInput('prompt'), 1);
    }

    public function test_空の文面ではオプションを作成できない(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OptionPrompt(null, 1, '登録名', PromptText::fromInput(''), 1);
    }

    public function test_名前と選択方式を指定してオプションブロックを作成する(): void
    {
        $group = new OptionPromptGroup(null, ' 表情 ', OptionSelectionMode::Multiple, 1);

        self::assertSame('表情', $group->name);
        self::assertSame(OptionSelectionMode::Multiple, $group->selectionMode);
    }

    public function test_オプションブロックのカテゴリだけを変更する(): void
    {
        $group = new OptionPromptGroup(1, '表情', OptionSelectionMode::Multiple, 2);

        $changed = $group->changeCategory(3);

        self::assertSame(1, $changed->id);
        self::assertSame('表情', $changed->name);
        self::assertSame(OptionSelectionMode::Multiple, $changed->selectionMode);
        self::assertSame(2, $changed->position);
        self::assertSame(3, $changed->categoryId);
        self::assertNull($group->categoryId);
    }
}
