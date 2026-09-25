<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClothingLoraApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_衣装LoRAとトリガーを登録して衣装用の選択肢だけを取得する(): void
    {
        $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => '人物',
            'fileName' => 'character.safetensors',
            'recommendedStrength' => 1,
        ])->assertCreated();
        $lora = $this->postJson(route('clothing-loras.store'), [
            'name' => 'ジャケット',
            'fileName' => 'jacket.safetensors',
            'recommendedStrength' => 0.8,
        ])->assertCreated();
        $trigger = $this->postJson(route('clothing-lora-triggers.store'), [
            'loraId' => $lora->json('id'),
            'name' => '標準',
            'content' => "jacket\nblack clothes",
        ])->assertCreated();

        $this->getJson(route('clothing-lora-options.index'))
            ->assertOk()
            ->assertJsonCount(1, 'loras')
            ->assertJsonCount(1, 'triggers')
            ->assertJsonPath('loras.0.name', 'ジャケット')
            ->assertJsonPath('loras.0.recommendedStrength', 0.8)
            ->assertJsonPath('triggers.0.id', $trigger->json('id'))
            ->assertJsonPath('triggers.0.content', 'jacket, black clothes,');
        $this->getJson(route('lora-prompt-options.index'))
            ->assertOk()
            ->assertJsonCount(1, 'loras')
            ->assertJsonPath('loras.0.name', '人物');
    }

    public function test_人物LoRAと衣装LoRAを通して同じファイル名は登録できない(): void
    {
        $fileName = 'same.safetensors';
        $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => '人物',
            'fileName' => $fileName,
            'recommendedStrength' => 1,
        ])->assertCreated();

        $this->postJson(route('clothing-loras.store'), [
            'name' => '衣装',
            'fileName' => $fileName,
            'recommendedStrength' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('fileName');
    }

    public function test_衣装LoRAの入口から人物LoRAとそのトリガーは変更できない(): void
    {
        $character = $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => '人物',
            'fileName' => 'character.safetensors',
            'recommendedStrength' => 1,
        ])->assertCreated();
        $trigger = $this->postJson(route('lora-triggers.store'), [
            'loraId' => $character->json('id'),
            'name' => '標準',
            'content' => 'character',
        ])->assertCreated();

        $this->putJson(route('clothing-loras.update', [
            'clothingLora' => $character->json('id'),
        ]), [
            'name' => '変更後',
            'fileName' => 'changed.safetensors',
            'recommendedStrength' => 0.8,
        ])->assertUnprocessable()->assertJsonValidationErrors('clothingLora');
        $this->deleteJson(route('clothing-lora-triggers.destroy', [
            'clothingLoraTrigger' => $trigger->json('id'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('clothingLoraTrigger');
        $this->assertDatabaseHas('loras', ['id' => $character->json('id'), 'name' => '人物']);
        $this->assertDatabaseHas('lora_triggers', ['id' => $trigger->json('id')]);
    }

    public function test_衣装LoRAを削除すると所属するトリガーも削除する(): void
    {
        $lora = $this->postJson(route('clothing-loras.store'), [
            'name' => '衣装',
            'fileName' => 'clothing.safetensors',
            'recommendedStrength' => 1,
        ])->assertCreated();
        $trigger = $this->postJson(route('clothing-lora-triggers.store'), [
            'loraId' => $lora->json('id'),
            'name' => '標準',
            'content' => 'clothing',
        ])->assertCreated();

        $this->deleteJson(route('clothing-loras.destroy', [
            'clothingLora' => $lora->json('id'),
        ]))->assertNoContent();

        $this->assertDatabaseMissing('loras', ['id' => $lora->json('id')]);
        $this->assertDatabaseMissing('lora_triggers', ['id' => $trigger->json('id')]);
    }

    public function test_衣装LoRAとトリガーを編集してトリガーだけを削除する(): void
    {
        $lora = $this->postJson(route('clothing-loras.store'), [
            'name' => '変更前衣装',
            'fileName' => 'before.safetensors',
            'recommendedStrength' => 1,
        ])->assertCreated();
        $trigger = $this->postJson(route('clothing-lora-triggers.store'), [
            'loraId' => $lora->json('id'),
            'name' => '変更前トリガー',
            'content' => 'before',
        ])->assertCreated();

        $this->putJson(route('clothing-loras.update', [
            'clothingLora' => $lora->json('id'),
        ]), [
            'name' => '変更後衣装',
            'fileName' => 'after.safetensors',
            'recommendedStrength' => 0.9,
        ])->assertOk()->assertJsonPath('recommendedStrength', 0.9);
        $this->putJson(route('clothing-lora-triggers.update', [
            'clothingLoraTrigger' => $trigger->json('id'),
        ]), [
            'loraId' => $lora->json('id'),
            'name' => '変更後トリガー',
            'content' => "after\nribbon",
        ])->assertOk()->assertJsonPath('content', 'after, ribbon,');
        $this->deleteJson(route('clothing-lora-triggers.destroy', [
            'clothingLoraTrigger' => $trigger->json('id'),
        ]))->assertNoContent();

        $this->assertDatabaseHas('loras', [
            'id' => $lora->json('id'),
            'kind' => 'clothing',
            'name' => '変更後衣装',
            'file_name' => 'after.safetensors',
            'recommended_strength_step' => 9,
        ]);
        $this->assertDatabaseMissing('lora_triggers', ['id' => $trigger->json('id')]);
    }

    public function test_衣装LoRAの強度とトリガーの入力条件に違反すると保存しない(): void
    {
        foreach ([-0.1, 0.15, 1.1] as $strength) {
            $this->postJson(route('clothing-loras.store'), [
                'name' => '衣装',
                'fileName' => "invalid-{$strength}.safetensors",
                'recommendedStrength' => $strength,
            ])->assertUnprocessable()->assertJsonValidationErrors('recommendedStrength');
        }
        $character = $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => '人物',
            'fileName' => 'character.safetensors',
            'recommendedStrength' => 1,
        ])->assertCreated();
        $this->postJson(route('clothing-lora-triggers.store'), [
            'loraId' => $character->json('id'),
            'name' => '標準',
            'content' => 'character',
        ])->assertUnprocessable()->assertJsonValidationErrors('loraId');
        $this->assertDatabaseMissing('loras', ['kind' => 'clothing']);
        $this->assertDatabaseCount('lora_triggers', 0);
    }

    public function test_LoRA用途には人物用と衣装用以外を保存できない(): void
    {
        $this->expectException(QueryException::class);

        DB::table('loras')->insert([
            'name' => '不正用途',
            'file_name' => 'invalid-kind.safetensors',
            'recommended_strength_step' => 10,
            'kind' => 'invalid',
        ]);
    }
}
