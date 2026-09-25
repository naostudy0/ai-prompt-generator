<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoraPromptOptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_LoRAとトリガーと服装を登録して選択肢として取得する(): void
    {
        $loraName = 'キャラクターA';
        $fileName = 'character-a.safetensors';
        $strength = 0.8;
        $triggerName = '標準';
        $triggerInput = "character a\nlong hair";
        $triggerContent = 'character a, long hair,';
        $outfitName = '制服';
        $outfitInput = 'school uniform,blue jacket';
        $outfitContent = 'school uniform, blue jacket,';

        $loraResponse = $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => $loraName,
            'fileName' => $fileName,
            'recommendedStrength' => $strength,
        ])->assertCreated();
        $loraId = $loraResponse->json('id');

        $triggerResponse = $this->postJson(route('lora-triggers.store'), [
            'loraId' => $loraId,
            'name' => $triggerName,
            'content' => $triggerInput,
        ])->assertCreated();
        $outfitResponse = $this->postJson(route('outfits.store'), [
            'loraId' => $loraId,
            'name' => $outfitName,
            'content' => $outfitInput,
        ])->assertCreated();

        $triggerResponse->assertJsonPath('content', $triggerContent);
        $outfitResponse->assertJsonPath('content', $outfitContent);
        $this->getJson(route('lora-prompt-options.index'))
            ->assertOk()
            ->assertExactJson([
                'loras' => [[
                    'id' => $loraId,
                    'modelFamilyId' => 1,
                    'name' => $loraName,
                    'fileName' => $fileName,
                    'recommendedStrength' => $strength,
                    'tags' => $this->loraTags($fileName),
                ]],
                'triggers' => [[
                    'id' => $triggerResponse->json('id'),
                    'loraId' => $loraId,
                    'name' => $triggerName,
                    'content' => $triggerContent,
                ]],
                'outfits' => [[
                    'id' => $outfitResponse->json('id'),
                    'loraId' => $loraId,
                    'name' => $outfitName,
                    'content' => $outfitContent,
                ]],
            ]);
    }

    public function test_LoRAの登録内容と推奨強度を編集する(): void
    {
        $loraId = $this->createLora('変更前', 'before.safetensors', 1);
        $updatedName = '変更後';
        $updatedFileName = 'after.safetensors';
        $updatedStrength = 0.9;

        $response = $this->putJson(route('loras.update', ['lora' => $loraId]), [
            'modelFamilyId' => 1,
            'name' => $updatedName,
            'fileName' => $updatedFileName,
            'recommendedStrength' => $updatedStrength,
        ]);

        $response->assertOk()->assertJson([
            'id' => $loraId,
            'name' => $updatedName,
            'fileName' => $updatedFileName,
            'recommendedStrength' => $updatedStrength,
        ]);
        $this->assertDatabaseHas('loras', [
            'id' => $loraId,
            'name' => $updatedName,
            'file_name' => $updatedFileName,
            'recommended_strength_step' => 9,
        ]);
    }

    public function test_同じファイル名のLoRAは登録できないが登録名の重複は許可する(): void
    {
        $sameName = '同じ登録名';
        $existingFileName = 'existing.safetensors';
        $this->createLora($sameName, $existingFileName, 1);

        $duplicateFileResponse = $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => '別名',
            'fileName' => $existingFileName,
            'recommendedStrength' => 1,
        ]);
        $sameNameResponse = $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => $sameName,
            'fileName' => 'another.safetensors',
            'recommendedStrength' => 1,
        ]);

        $duplicateFileResponse->assertUnprocessable()->assertJsonValidationErrors('fileName');
        $sameNameResponse->assertCreated();
        $this->assertDatabaseCount('loras', 2);
    }

    public function test_範囲外または0点1刻みでない推奨強度は登録できない(): void
    {
        foreach ([-0.1, 0.15, 1.1] as $invalidStrength) {
            $response = $this->postJson(route('loras.store'), [
                'modelFamilyId' => 1,
                'name' => 'キャラクター',
                'fileName' => "character-{$invalidStrength}",
                'recommendedStrength' => $invalidStrength,
            ]);

            $response->assertUnprocessable()->assertJsonValidationErrors('recommendedStrength');
        }

        $this->assertDatabaseCount('loras', 0);
    }

    public function test_空白とカンマだけのトリガーと服装は登録できない(): void
    {
        $loraId = $this->createLora('キャラクター', 'character.safetensors', 1);
        $content = " , ,\n";

        $triggerResponse = $this->postJson(route('lora-triggers.store'), [
            'loraId' => $loraId,
            'name' => '標準',
            'content' => $content,
        ]);
        $outfitResponse = $this->postJson(route('outfits.store'), [
            'loraId' => null,
            'name' => '制服',
            'content' => $content,
        ]);

        $triggerResponse->assertUnprocessable()->assertJsonValidationErrors('content');
        $outfitResponse->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->assertDatabaseCount('lora_triggers', 0);
        $this->assertDatabaseCount('outfits', 0);
    }

    public function test_LoRAを削除すると紐づくトリガーと服装も削除する(): void
    {
        $loraId = $this->createLora('キャラクター', 'character.safetensors', 1);
        $triggerId = $this->postJson(route('lora-triggers.store'), [
            'loraId' => $loraId,
            'name' => '標準',
            'content' => 'character',
        ])->json('id');
        $outfitId = $this->postJson(route('outfits.store'), [
            'loraId' => $loraId,
            'name' => '制服',
            'content' => 'uniform',
        ])->json('id');

        $this->deleteJson(route('loras.destroy', ['lora' => $loraId]))->assertNoContent();

        $this->assertDatabaseMissing('loras', ['id' => $loraId]);
        $this->assertDatabaseMissing('lora_triggers', ['id' => $triggerId]);
        $this->assertDatabaseMissing('outfits', ['id' => $outfitId]);
    }

    public function test_トリガーと服装を編集して個別に削除する(): void
    {
        $loraId = $this->createLora('キャラクター', 'character.safetensors', 1);
        $triggerId = $this->postJson(route('lora-triggers.store'), [
            'loraId' => $loraId,
            'name' => '変更前トリガー',
            'content' => 'before trigger',
        ])->assertCreated()->json('id');
        $outfitId = $this->postJson(route('outfits.store'), [
            'loraId' => $loraId,
            'name' => '変更前服装',
            'content' => 'before outfit',
        ])->assertCreated()->json('id');

        $this->putJson(route('lora-triggers.update', ['trigger' => $triggerId]), [
            'loraId' => $loraId,
            'name' => '変更後トリガー',
            'content' => "updated trigger\nlong hair",
        ])->assertOk()->assertJson([
            'id' => $triggerId,
            'loraId' => $loraId,
            'name' => '変更後トリガー',
            'content' => 'updated trigger, long hair,',
        ]);
        $this->putJson(route('outfits.update', ['outfit' => $outfitId]), [
            'loraId' => $loraId,
            'name' => '変更後服装',
            'content' => 'updated outfit',
        ])->assertOk()->assertJson([
            'id' => $outfitId,
            'loraId' => $loraId,
            'name' => '変更後服装',
            'content' => 'updated outfit,',
        ]);
        $this->assertDatabaseHas('lora_triggers', [
            'id' => $triggerId,
            'name' => '変更後トリガー',
            'content' => 'updated trigger, long hair,',
        ]);
        $this->assertDatabaseHas('outfits', [
            'id' => $outfitId,
            'lora_id' => $loraId,
            'name' => '変更後服装',
            'content' => 'updated outfit,',
        ]);

        $this->deleteJson(route('lora-triggers.destroy', ['trigger' => $triggerId]))->assertNoContent();
        $this->deleteJson(route('outfits.destroy', ['outfit' => $outfitId]))->assertNoContent();
        $this->assertDatabaseMissing('lora_triggers', ['id' => $triggerId]);
        $this->assertDatabaseMissing('outfits', ['id' => $outfitId]);
    }

    public function test_名前が空白だけのトリガーと服装は登録できない(): void
    {
        $loraId = $this->createLora('キャラクター', 'character.safetensors', 1);

        $this->postJson(route('lora-triggers.store'), [
            'loraId' => $loraId,
            'name' => '   ',
            'content' => 'character',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('outfits.store'), [
            'loraId' => $loraId,
            'name' => "\t",
            'content' => 'uniform',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_タグの構文を壊す文字を含むファイル名は登録できない(): void
    {
        foreach (['invalid:name', '<invalid>', 'invalid,name'] as $fileName) {
            $this->postJson(route('loras.store'), [
                'modelFamilyId' => 1,
                'name' => 'キャラクター',
                'fileName' => $fileName,
                'recommendedStrength' => 1,
            ])->assertUnprocessable()->assertJsonValidationErrors('fileName');
        }

        $this->assertDatabaseCount('loras', 0);
    }

    public function test_LoRAの登録名とファイル名は空白だけでは登録できない(): void
    {
        foreach ([
            ['name' => '   ', 'fileName' => 'valid.safetensors', 'invalidField' => 'name'],
            ['name' => 'キャラクター', 'fileName' => " \t ", 'invalidField' => 'fileName'],
        ] as $input) {
            $this->postJson(route('loras.store'), [
                'modelFamilyId' => 1,
                'name' => $input['name'],
                'fileName' => $input['fileName'],
                'recommendedStrength' => 1,
            ])->assertUnprocessable()->assertJsonValidationErrors($input['invalidField']);
        }

        $this->assertDatabaseCount('loras', 0);
    }

    public function test_推奨強度の境界値0と1を登録できる(): void
    {
        $zeroId = $this->createLora('強度0', 'zero.safetensors', 0);
        $oneId = $this->createLora('強度1', 'one.safetensors', 1);

        $this->assertDatabaseHas('loras', ['id' => $zeroId, 'recommended_strength_step' => 0]);
        $this->assertDatabaseHas('loras', ['id' => $oneId, 'recommended_strength_step' => 10]);
    }

    public function test_DBは範囲外の推奨強度を保存しない(): void
    {
        $this->expectException(QueryException::class);

        DB::table('loras')->insert([
            'name' => '不正な強度',
            'file_name' => 'invalid-strength.safetensors',
            'recommended_strength_step' => 11,
        ]);
    }

    public function test_存在しないLoRAへのトリガーと服装は登録できない(): void
    {
        $missingLoraId = 999;

        $triggerResponse = $this->postJson(route('lora-triggers.store'), [
            'loraId' => $missingLoraId,
            'name' => '標準',
            'content' => 'character',
        ]);
        $outfitResponse = $this->postJson(route('outfits.store'), [
            'loraId' => $missingLoraId,
            'name' => '制服',
            'content' => 'uniform',
        ]);

        $triggerResponse->assertUnprocessable()->assertJsonValidationErrors('loraId');
        $outfitResponse->assertUnprocessable()->assertJsonValidationErrors('loraId');
    }

    private function createLora(string $name, string $fileName, int|float $strength): int
    {
        $response = $this->postJson(route('loras.store'), [
            'modelFamilyId' => 1,
            'name' => $name,
            'fileName' => $fileName,
            'recommendedStrength' => $strength,
        ])->assertCreated();

        return (int) $response->json('id');
    }

    /** @return list<string> */
    private function loraTags(string $fileName): array
    {
        return array_map(
            fn (int $step): string => sprintf(
                '<lora:%s:%s>,',
                $fileName,
                $step === 10 ? '1' : number_format($step / 10, 1),
            ),
            range(0, 10),
        );
    }
}
