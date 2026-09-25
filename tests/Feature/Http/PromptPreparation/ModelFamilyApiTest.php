<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelFamilyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_系統ごとにpositiveとnegativeを独立して登録する(): void
    {
        $this->seed();
        $anima = $this->postJson(route('model-families.store'), ['name' => 'anima'])
            ->assertCreated()->json('id');

        $this->getJson(route('model-families.default-prompts.index', $anima))
            ->assertOk()->assertExactJson(['positive' => '', 'negative' => '']);
        $this->putJson(route('model-families.default-prompts.update', [$anima, 'positive']), [
            'content' => 'anime quality',
        ])->assertOk()->assertJsonPath('content', 'anime quality,');
        $this->putJson(route('model-families.default-prompts.update', [$anima, 'negative']), [
            'content' => 'bad eyes',
        ])->assertOk()->assertJsonPath('content', 'bad eyes,');

        $this->getJson(route('model-families.default-prompts.index', $anima))
            ->assertExactJson(['positive' => 'anime quality,', 'negative' => 'bad eyes,']);
        $this->getJson(route('model-families.default-prompts.index', 1))
            ->assertJsonPath('positive', 'masterpiece, best quality, highres,')
            ->assertJsonPath('negative', 'bad anatomy, bad hands, extra fingers, missing fingers, extra limbs,');
    }

    public function test_人物LoRAの系統を変更しても所属が保たれる(): void
    {
        $anima = $this->postJson(route('model-families.store'), ['name' => 'anima'])
            ->assertCreated()->json('id');
        $lora = $this->postJson(route('loras.store'), [
            'name' => '人物',
            'fileName' => 'person.safetensors',
            'recommendedStrength' => 1,
            'modelFamilyId' => $anima,
        ])->assertCreated()->json('id');

        $this->getJson(route('lora-prompt-options.index'))
            ->assertJsonPath('loras.0.modelFamilyId', $anima);
        $this->deleteJson(route('model-families.destroy', $anima))->assertStatus(409);
        $this->putJson(route('model-families.update', $anima), ['name' => 'Anima'])
            ->assertOk()->assertJsonPath('name', 'Anima');
        $this->getJson(route('lora-prompt-options.index'))
            ->assertJsonPath('loras.0.modelFamilyId', $anima);
        $this->putJson(route('loras.update', $lora), [
            'name' => '人物',
            'fileName' => 'person.safetensors',
            'recommendedStrength' => 1,
            'modelFamilyId' => 1,
        ])->assertOk();
        $this->getJson(route('lora-prompt-options.index'))
            ->assertJsonPath('loras.0.modelFamilyId', 1);
        $this->deleteJson(route('model-families.destroy', $anima))->assertNoContent();
        $this->getJson(route('model-families.default-prompts.index', $anima))->assertNotFound();
    }

    public function test_系統の重複名と存在しない所属は登録できない(): void
    {
        $this->postJson(route('model-families.store'), ['name' => 'ILLUSTRIOUS'])
            ->assertUnprocessable();
        $this->postJson(route('loras.store'), [
            'name' => '人物',
            'fileName' => 'person.safetensors',
            'recommendedStrength' => 1,
            'modelFamilyId' => 999,
        ])->assertUnprocessable();
        $this->deleteJson(route('model-families.destroy', 1))->assertStatus(409);
    }
}
