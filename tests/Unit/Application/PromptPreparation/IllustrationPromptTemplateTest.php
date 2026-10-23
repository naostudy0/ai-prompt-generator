<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\IllustrationPromptTemplate;
use PHPUnit\Framework\TestCase;

final class IllustrationPromptTemplateTest extends TestCase
{
    public function test_入力済み項目だけを固定の見出しと順序で整形する(): void
    {
        $template = new IllustrationPromptTemplate(
            character: '20代女性、黒髪ロング、青い瞳',
            action: '窓の外を眺めている。',
            background: '',
            composition: '斜め前からの上半身',
            atmosphere: '',
            constraints: '女性1人のみ。涙は描かない。',
        );

        self::assertSame(<<<'TEXT'
【人物】
20代女性、黒髪ロング、青い瞳

【動作・表情】
窓の外を眺めている。

【構図】
斜め前からの上半身

【必須条件】
女性1人のみ。涙は描かない。
TEXT, $template->toDescription());
    }
}
