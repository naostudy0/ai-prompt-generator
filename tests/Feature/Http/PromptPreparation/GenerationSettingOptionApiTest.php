<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGenerationSettings;
use Tests\TestCase;

class GenerationSettingOptionApiTest extends TestCase
{
    use CreatesGenerationSettings;
    use RefreshDatabase;

    public function test_系統ごとの生成設定を登録編集一覧取得削除する(): void
    {
        $created = $this->postJson(route('model-families.generation-setting-options.store', 1), [
            'kind' => 'checkpoint',
            'value' => 'model.safetensors',
        ])->assertCreated()->json();

        $this->getJson(route('model-families.generation-setting-options.index', 1))
            ->assertOk()
            ->assertJsonPath('options.0.value', 'model.safetensors');

        $this->putJson(route('generation-setting-options.update', $created['id']), [
            'modelFamilyId' => 1,
            'kind' => 'checkpoint',
            'value' => 'updated.safetensors',
        ])->assertOk()->assertJsonPath('value', 'updated.safetensors');

        $this->deleteJson(route('generation-setting-options.destroy', $created['id']))
            ->assertNoContent();
    }

    public function test_人物LoRAは同じ系統の三種類の生成設定を必須とする(): void
    {
        $this->postJson(route('loras.store'), [
            'name' => 'character',
            'fileName' => 'character',
            'recommendedStrength' => 1,
            'modelFamilyId' => 1,
        ])->assertUnprocessable();

        $settings = $this->generationSettings(1);
        $this->postJson(route('model-families.store'), ['name' => 'Anima'])->assertCreated();
        $wrongFamily = $this->generationSettings(2);
        $this->postJson(route('loras.store'), [
            'name' => 'character',
            'fileName' => 'character',
            'recommendedStrength' => 1,
            'modelFamilyId' => 1,
            ...$settings,
            'checkpointOptionId' => $wrongFamily['checkpointOptionId'],
        ])->assertUnprocessable();
    }

    public function test_人物LoRAが使用中の生成設定は削除できない(): void
    {
        $settings = $this->generationSettings();
        $this->postJson(route('loras.store'), [
            'name' => 'character',
            'fileName' => 'character',
            'recommendedStrength' => 1,
            'modelFamilyId' => 1,
            ...$settings,
        ])->assertCreated();

        $this->deleteJson(route('generation-setting-options.destroy', $settings['checkpointOptionId']))
            ->assertStatus(409);
    }
}
