<?php

namespace Tests\Unit\Domain\PromptPreparation;

use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPromptText;
use PHPUnit\Framework\TestCase;

class DefaultPromptTextTest extends TestCase
{
    public function test_カンマと改行を区切りとして文面を整形する(): void
    {
        $input = "abc,def\nghi,";
        $expected = 'abc, def, ghi,';

        $text = DefaultPromptText::fromInput($input);

        $this->assertSame($expected, $text->value);
        $this->assertTrue($text->formatted);
    }

    public function test_空の要素と完全一致する重複を取り除く(): void
    {
        $input = 'abc,, def, abc,';
        $expected = 'abc, def,';

        $text = DefaultPromptText::fromInput($input);

        $this->assertSame($expected, $text->value);
        $this->assertTrue($text->formatted);
    }

    public function test_丸括弧内を分割せず括弧全体の重複だけを取り除く(): void
    {
        $input = '(abc,abc), abc, (abc,abc)';
        $expected = '(abc,abc), abc,';

        $text = DefaultPromptText::fromInput($input);

        $this->assertSame($expected, $text->value);
        $this->assertTrue($text->formatted);
    }

    public function test_入れ子の丸括弧とエスケープした括弧を一まとまりとして保持する(): void
    {
        $input = '(abc,(def,ghi),\)), jkl';
        $expected = '(abc,(def,ghi),\)), jkl,';

        $text = DefaultPromptText::fromInput($input);

        $this->assertSame($expected, $text->value);
        $this->assertTrue($text->formatted);
    }

    public function test_対応しない丸括弧を含む場合は原文を保持する(): void
    {
        $input = '(abc,def';

        $text = DefaultPromptText::fromInput($input);

        $this->assertSame($input, $text->value);
        $this->assertFalse($text->formatted);
    }

    public function test_空白と区切りだけの場合は空文字列にする(): void
    {
        $input = " , ,\n ";

        $text = DefaultPromptText::fromInput($input);

        $this->assertSame('', $text->value);
        $this->assertTrue($text->formatted);
    }
}
