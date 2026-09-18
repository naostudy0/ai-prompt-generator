<?php

namespace Tests\Feature;

use Tests\TestCase;

class TopPageTest extends TestCase
{
    public function test_トップページに入力と出力の操作画面を表示する(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('プロンプトを組み立てる')
            ->assertDontSee('使う文面を選び、画像生成ツールのpositive／negative欄へコピーできます。')
            ->assertSee('入力：使う項目を選ぶ')
            ->assertDontSee('デフォルト文面を選択し、必要に応じて内容を編集してください。')
            ->assertSee('positiveのデフォルトを編集')
            ->assertSee('negativeのデフォルトを編集')
            ->assertSee('LoRA')
            ->assertSee('LoRAを検索')
            ->assertSee('トリガー')
            ->assertSee('服装')
            ->assertSee('推奨強度')
            ->assertSee('描写')
            ->assertSee('ブロックを追加')
            ->assertSee('服装を検索')
            ->assertSee('プロンプトを表示')
            ->assertSee('出力：')
            ->assertSee('画像生成ツールへコピー')
            ->assertDontSee('出力は直接編集できます。調整後、それぞれ対応する欄へコピーしてください。')
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-label="positiveの出力"', false)
            ->assertSee('aria-label="negativeの出力"', false)
            ->assertSee('aria-label="positiveのデフォルトを選択"', false)
            ->assertSee('aria-label="negativeのデフォルトを編集"', false)
            ->assertSee('aria-label="positiveの出力をコピー"', false)
            ->assertSee('aria-label="negativeの出力をコピー"', false)
            ->assertSee('aria-label="LoRA選択肢を横にスクロール"', false)
            ->assertSee('aria-label="トリガー選択肢を横にスクロール"', false)
            ->assertSee('aria-label="服装選択肢を横にスクロール"', false)
            ->assertSee('data-output-section', false)
            ->assertSee('data-toast', false)
            ->assertSee(route('default-prompts.index'), false)
            ->assertSee(route('default-prompts.update', ['polarity' => 'positive']), false)
            ->assertSee(route('default-prompts.update', ['polarity' => 'negative']), false)
            ->assertSee(route('lora-prompt-options.index'), false)
            ->assertSee(route('loras.store'), false)
            ->assertSee(route('lora-triggers.store'), false)
            ->assertSee(route('outfits.store'), false)
            ->assertSee(route('prompt-options.index'), false)
            ->assertDontSee('<h3>デフォルト</h3>', false)
            ->assertDontSee('data-preview', false);
        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertSame(2, substr_count($content, 'aria-pressed="true"'));
    }
}
