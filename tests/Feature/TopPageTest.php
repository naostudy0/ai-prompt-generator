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
            ->assertSee('オプションを管理')
            ->assertSee('服装を検索')
            ->assertSee('衣装LoRA')
            ->assertSee('衣装LoRAを検索')
            ->assertSee('セクション')
            ->assertSee('選択中')
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
            ->assertSee('data-section-navigation-list', false)
            ->assertSee('data-selection-summary-list', false)
            ->assertSee('data-sidebar-display', false)
            ->assertSee('プロンプトを作成')
            ->assertSee('AIでプロンプトを作成')
            ->assertSee('シナリオを作成')
            ->assertSee('1. シナリオ案（日本語）')
            ->assertSee('2. 画像生成プロンプト（英語）')
            ->assertSee('全場面の画像生成プロンプトを生成')
            ->assertSee(route('illustration-scenarios.plan'), false)
            ->assertSee(route('illustration-scenarios.prompts.generate'), false)
            ->assertSee(route('illustration-scenarios.scenes.prompt'), false)
            ->assertSee('現在のデフォルトプロンプトを含める')
            ->assertSee('data-illustration-prompt-dialog', false)
            ->assertSee(route('illustration-prompts.generate'), false)
            ->assertSee(route('illustration-prompt-tag-groups.store'), false)
            ->assertSee('data-illustration-prompt-tag-groups-url', false)
            ->assertSee('data-favorite-save', false)
            ->assertSee('data-favorite-open', false)
            ->assertSee('グループ')
            ->assertSee('保存済みグループなし')
            ->assertSee('data-character-variant-group-select', false)
            ->assertSee('data-character-variant-group-load', false)
            ->assertSee('data-character-variant-group-delete', false)
            ->assertSee('data-character-variant-group-name', false)
            ->assertSee('maxlength="100"', false)
            ->assertSee('data-character-variant-group-save', false)
            ->assertSee('現在のチェックで保存')
            ->assertSee('data-character-variant-select-all', false)
            ->assertSee('data-character-variant-clear-all', false)
            ->assertSee('data-character-variant-count', false)
            ->assertSee(route('favorite-prompts.index'), false)
            ->assertSee('data-toast', false)
            ->assertSee(route('default-prompts.index'), false)
            ->assertSee(route('default-prompts.update', ['polarity' => 'positive']), false)
            ->assertSee(route('default-prompts.update', ['polarity' => 'negative']), false)
            ->assertSee(route('lora-prompt-options.index'), false)
            ->assertSee(route('clothing-lora-options.index'), false)
            ->assertSee(route('clothing-loras.store'), false)
            ->assertSee(route('clothing-lora-triggers.store'), false)
            ->assertSee(route('loras.store'), false)
            ->assertSee(route('lora-triggers.store'), false)
            ->assertSee(route('outfits.store'), false)
            ->assertSee(route('prompt-options.index'), false)
            ->assertSee(route('prompt-options.manage'), false)
            ->assertDontSee('<h3>デフォルト</h3>', false)
            ->assertDontSee('data-preview', false);
        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertSame(2, substr_count($content, 'aria-pressed="true"'));
    }

    public function test_オプション管理を専用ページに表示する(): void
    {
        $this->get(route('prompt-options.manage'))
            ->assertOk()
            ->assertSee('オプションを管理')
            ->assertSee('選択画面に戻る')
            ->assertSee('カテゴリ')
            ->assertSee('ブロックと項目')
            ->assertSee('data-prompt-option-management', false)
            ->assertSee(route('prompt-preparation'), false)
            ->assertSee(route('prompt-option-categories.index'), false);
    }
}
