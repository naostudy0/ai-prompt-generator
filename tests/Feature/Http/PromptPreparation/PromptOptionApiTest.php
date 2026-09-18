<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptOptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_初期カテゴリを名前と選択方式と順序付きで取得する(): void
    {
        $this->getJson(route('prompt-options.index'))->assertOk()
            ->assertJsonPath('groups.0.name', '表情')
            ->assertJsonPath('groups.0.selectionMode', 'multiple')
            ->assertJsonPath('groups.1.name', '視線')
            ->assertJsonPath('groups.1.selectionMode', 'single')
            ->assertJsonPath('groups.5.name', 'オプション1');
    }

    public function test_名前と選択方式を指定してブロックを追加し編集する(): void
    {
        $created = $this->postJson(route('prompt-option-groups.store'), [
            'name' => '画質', 'selectionMode' => 'multiple',
        ])->assertCreated()->json();

        self::assertSame(7, $created['position']);
        $this->putJson(route('prompt-option-groups.update', ['group' => $created['id']]), [
            'name' => '表現', 'selectionMode' => 'single',
        ])->assertOk()->assertJson(['name' => '表現', 'selectionMode' => 'single']);
    }

    public function test_項目を整形して登録し編集して削除する(): void
    {
        $id = $this->create('笑顔', "smile\nopen mouth", 1);
        $this->getJson(route('prompt-options.index'))
            ->assertJsonPath('groups.0.options.0', [
                'id' => $id, 'position' => 1, 'name' => '笑顔', 'content' => 'smile, open mouth,',
            ]);

        $this->putJson(route('prompt-options.update', ['option' => $id]), [
            'name' => '微笑み', 'content' => 'soft smile',
        ])->assertOk()->assertJson(['id' => $id, 'name' => '微笑み', 'content' => 'soft smile,']);
        $this->deleteJson(route('prompt-options.destroy', ['option' => $id]))->assertNoContent();
        $this->assertDatabaseMissing('option_prompts', ['id' => $id]);
    }

    public function test_ブロックと項目を並べ替えて項目を別ブロックへ移動する(): void
    {
        $expression = $this->create('笑顔', 'smile', 1);
        $gaze = $this->create('カメラ目線', 'looking at viewer', 2);

        $this->patchJson(route('prompt-option-groups.move', ['group' => 2]), ['beforeGroupId' => 1])
            ->assertNoContent();
        $this->patchJson(route('prompt-options.move', ['option' => $expression]), [
            'targetGroupId' => 2, 'beforeOptionId' => $gaze,
        ])->assertNoContent();

        $this->getJson(route('prompt-options.index'))
            ->assertJsonPath('groups.0.id', 2)
            ->assertJsonPath('groups.0.options.0.id', $expression)
            ->assertJsonPath('groups.0.options.1.id', $gaze);
    }

    public function test_別ブロックの項目を移動先として指定できない(): void
    {
        $expression = $this->create('笑顔', 'smile', 1);
        $gaze = $this->create('カメラ目線', 'looking at viewer', 2);

        $this->patchJson(route('prompt-options.move', ['option' => $expression]), [
            'targetGroupId' => 1, 'beforeOptionId' => $gaze,
        ])->assertNotFound();
    }

    public function test_項目を削除すると残る項目の表示順を詰める(): void
    {
        $first = $this->create('笑顔', 'smile', 1);
        $second = $this->create('口を開ける', 'open mouth', 1);
        $third = $this->create('赤面', 'blush', 1);

        $this->deleteJson(route('prompt-options.destroy', ['option' => $second]))->assertNoContent();

        $this->assertDatabaseHas('option_prompts', ['id' => $first, 'position' => 1]);
        $this->assertDatabaseHas('option_prompts', ['id' => $third, 'position' => 2]);
        $added = $this->create('涙', 'tears', 1);
        $this->assertDatabaseHas('option_prompts', ['id' => $added, 'position' => 3]);
    }

    public function test_空のブロック名と不正な選択方式は保存できない(): void
    {
        $this->postJson(route('prompt-option-groups.store'), [
            'name' => ' ', 'selectionMode' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'selectionMode']);
    }

    private function create(string $name, string $content, int $groupId): int
    {
        return (int) $this->postJson(route('prompt-options.store'), [
            'name' => $name, 'content' => $content, 'groupId' => $groupId,
        ])->assertCreated()->json('id');
    }
}
