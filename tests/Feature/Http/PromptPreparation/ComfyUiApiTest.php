<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGenerationSettings;
use Tests\TestCase;

class ComfyUiApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesGenerationSettings;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.comfyui.base_url' => 'http://comfy.test']);
    }

    public function test_系統別ワークフローを保存取得削除する(): void
    {
        $this->seed();
        $this->uploadWorkflow(1)->assertOk();

        $this->getJson(route('model-families.comfyui-workflow.show', 1))
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('fileName', 'workflow.json')
            ->assertJsonPath('mappings.positive.nodeId', '37');
        Storage::disk('local')->assertExists(
            (string) \DB::table('comfy_ui_workflows')->where('model_family_id', 1)->value('storage_path'),
        );

        $this->deleteJson(route('model-families.comfyui-workflow.destroy', 1))->assertNoContent();
        $this->getJson(route('model-families.comfyui-workflow.show', 1))
            ->assertExactJson(['configured' => false]);
    }

    public function test_不正な入力位置では既存ワークフローを置き換えない(): void
    {
        $this->seed();
        $this->uploadWorkflow(1)->assertOk();
        $path = \DB::table('comfy_ui_workflows')->where('model_family_id', 1)->value('storage_path');

        $this->post(route('model-families.comfyui-workflow.update', 1), [
            '_method' => 'PUT',
            'workflow' => UploadedFile::fake()->createWithContent('bad.json', $this->workflowJson()),
            'positiveNodeId' => 'missing', 'positiveInputName' => 'prompt',
            'negativeNodeId' => '118', 'negativeInputName' => 'string',
            'seedNodeId' => '39', 'seedInputName' => 'value',
            'checkpointMappings' => [['nodeId' => '49', 'inputName' => 'ckpt_name']],
            'samplerMappings' => [['nodeId' => '102', 'inputName' => 'sampler_name']],
            'schedulerMappings' => [['nodeId' => '102', 'inputName' => 'scheduler']],
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame($path, \DB::table('comfy_ui_workflows')->where('model_family_id', 1)->value('storage_path'));
    }

    public function test_必須の生成設定マッピングが空なら具体的な理由を返す(): void
    {
        $this->seed();

        $response = $this->post(route('model-families.comfyui-workflow.update', 1), [
            '_method' => 'PUT',
            'workflow' => UploadedFile::fake()->createWithContent('workflow.json', $this->workflowJson()),
            'positiveNodeId' => '37', 'positiveInputName' => 'prompt',
            'negativeNodeId' => '118', 'negativeInputName' => 'string',
            'seedNodeId' => '39', 'seedInputName' => 'value',
            'checkpointMappings' => [['nodeId' => '', 'inputName' => 'ckpt_name']],
            'samplerMappings' => [['nodeId' => '102', 'inputName' => 'sampler_name']],
            'schedulerMappings' => [['nodeId' => '102', 'inputName' => 'scheduler']],
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['checkpointMappings.0.nodeId']);
        $this->assertSame(
            'checkpointのノードIDを入力してください。',
            $response->json()['errors']['checkpointMappings.0.nodeId'][0],
        );
    }

    public function test_空の両プロンプトとランダムseedをComfyUIへ送信する(): void
    {
        $this->seed();
        $this->uploadWorkflow(1)->assertOk();
        Http::fake(['http://comfy.test/prompt' => Http::response([
            'prompt_id' => '11111111-1111-1111-1111-111111111111',
            'number' => 4,
            'node_errors' => [],
        ])]);

        $this->postJson(route('comfyui-prompts.store'), [
            'modelFamilyId' => 1, 'positive' => '', 'negative' => '',
        ])->assertStatus(202)->assertJsonPath('queueNumber', 4);

        Http::assertSent(function (Request $request): bool {
            $prompt = $request->data()['prompt'];

            return $request->url() === 'http://comfy.test/prompt'
                && $prompt['37']['inputs']['prompt'] === ''
                && $prompt['118']['inputs']['string'] === ''
                && $prompt['49']['inputs']['ckpt_name'] === 'original.safetensors'
                && $prompt['43']['inputs']['string'] === 'tmp/original'
                && is_int($prompt['39']['inputs']['value']);
        });
    }

    public function test_一括送信は途中失敗後も残りを送信する(): void
    {
        $this->seed();
        $this->uploadWorkflow(1)->assertOk();
        $loraIds = $this->createCharacterLoras(3);
        Http::fakeSequence()
            ->push(['prompt_id' => '11111111-1111-1111-1111-111111111111', 'number' => 1, 'node_errors' => []])
            ->push(['error' => ['message' => 'invalid']], 400)
            ->push(['prompt_id' => '33333333-3333-3333-3333-333333333333', 'number' => 3, 'node_errors' => []]);
        $items = collect(range(1, 3))->map(fn (int $id): array => [
            'candidateKey' => "candidate-{$id}", 'modelFamilyId' => 1,
            'loraId' => $loraIds[$id - 1],
            'positive' => "positive {$id}", 'negative' => '',
        ])->all();

        $this->postJson(route('comfyui-prompts.batch'), ['items' => $items])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'accepted')
            ->assertJsonPath('results.1.status', 'failed')
            ->assertJsonPath('results.2.status', 'accepted');
        Http::assertSentCount(3);
        $seeds = collect(Http::recorded())->map(
            fn (array $pair): mixed => $pair[0]->data()['prompt']['39']['inputs']['value'],
        );
        $seeds->each(fn (mixed $seed) => $this->assertIsInt($seed));
        $this->assertCount(3, $seeds->unique());
        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded);
        $firstExchange = $recorded[0];
        $this->assertIsArray($firstExchange);
        $firstPrompt = $firstExchange[0]->data()['prompt'];
        $this->assertSame('test-checkpoint.safetensors', $firstPrompt['49']['inputs']['ckpt_name']);
        $this->assertSame('euler_ancestral', $firstPrompt['102']['inputs']['sampler_name']);
        $this->assertSame('euler_ancestral', $firstPrompt['142']['inputs']['sampler_name']);
        $this->assertSame('normal', $firstPrompt['102']['inputs']['scheduler']);
        $this->assertSame('normal', $firstPrompt['142']['inputs']['scheduler']);
        $this->assertSame('tmp/キャラクター 1/キャラクター 1', $firstPrompt['43']['inputs']['string']);
    }

    public function test_一括送信は100件を超える要求を拒否する(): void
    {
        $items = collect(range(1, 101))->map(fn (int $id): array => [
            'candidateKey' => "candidate-{$id}", 'modelFamilyId' => 1,
            'loraId' => $id,
            'positive' => '', 'negative' => '',
        ])->all();

        $this->postJson(route('comfyui-prompts.batch'), ['items' => $items])->assertUnprocessable();
    }

    public function test_系統が一致しない人物LoRAは失敗し一括送信の残りを続ける(): void
    {
        $this->seed();
        $this->uploadWorkflow(1)->assertOk();
        $loraId = $this->createCharacterLoras(1)[0];
        $otherFamilyId = (int) \DB::table('model_families')->insertGetId([
            'name' => 'other',
            'normalized_name' => 'other',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake(['http://comfy.test/prompt' => Http::response([
            'prompt_id' => '11111111-1111-1111-1111-111111111111',
            'number' => 1,
            'node_errors' => [],
        ])]);

        $this->postJson(route('comfyui-prompts.store'), [
            'modelFamilyId' => $otherFamilyId,
            'loraId' => $loraId,
            'positive' => '',
            'negative' => '',
        ])->assertUnprocessable();

        $this->postJson(route('comfyui-prompts.batch'), ['items' => [
            [
                'candidateKey' => 'invalid', 'modelFamilyId' => $otherFamilyId, 'loraId' => $loraId,
                'positive' => '', 'negative' => '',
            ],
            [
                'candidateKey' => 'valid', 'modelFamilyId' => 1, 'loraId' => $loraId,
                'positive' => '', 'negative' => '',
            ],
        ]])->assertOk()
            ->assertJsonPath('results.0.status', 'failed')
            ->assertJsonPath('results.1.status', 'accepted');
        Http::assertSentCount(1);
    }

    /** @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function uploadWorkflow(int $familyId): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('model-families.comfyui-workflow.update', $familyId), [
            '_method' => 'PUT',
            'workflow' => UploadedFile::fake()->createWithContent('workflow.json', $this->workflowJson()),
            'positiveNodeId' => '37', 'positiveInputName' => 'prompt',
            'negativeNodeId' => '118', 'negativeInputName' => 'string',
            'seedNodeId' => '39', 'seedInputName' => 'value',
            'checkpointMappings' => [['nodeId' => '49', 'inputName' => 'ckpt_name']],
            'samplerMappings' => [
                ['nodeId' => '102', 'inputName' => 'sampler_name'],
                ['nodeId' => '142', 'inputName' => 'sampler_name'],
            ],
            'schedulerMappings' => [
                ['nodeId' => '102', 'inputName' => 'scheduler'],
                ['nodeId' => '142', 'inputName' => 'scheduler'],
            ],
            'outputFilenamePrefixMappings' => [['nodeId' => '43', 'inputName' => 'string']],
        ], ['Accept' => 'application/json']);
    }

    private function workflowJson(): string
    {
        return json_encode([
            '37' => ['inputs' => ['prompt' => '', 'seed' => ['39', 0]], 'class_type' => 'Wildcard Processor'],
            '118' => ['inputs' => ['string' => ''], 'class_type' => 'String Literal'],
            '39' => ['inputs' => ['value' => 123], 'class_type' => 'PrimitiveInt'],
            '49' => ['inputs' => ['ckpt_name' => 'original.safetensors'], 'class_type' => 'CheckpointLoaderSimple'],
            '102' => ['inputs' => ['sampler_name' => 'original', 'scheduler' => 'original'], 'class_type' => 'KSamplerSelect'],
            '142' => ['inputs' => ['sampler_name' => 'original', 'scheduler' => 'original'], 'class_type' => 'KSamplerSelect'],
            '43' => ['inputs' => ['string' => 'tmp/original'], 'class_type' => 'String Literal'],
        ], JSON_THROW_ON_ERROR);
    }

    /** @return list<int> */
    private function createCharacterLoras(int $count): array
    {
        $settings = $this->generationSettings();

        return array_values(collect(range(1, $count))->map(fn (int $number): int => (int) \DB::table('loras')->insertGetId([
            'name' => "キャラクター {$number}",
            'file_name' => "character-{$number}",
            'recommended_strength_step' => 10,
            'kind' => 'character',
            'model_family_id' => 1,
            'checkpoint_option_id' => $settings['checkpointOptionId'],
            'sampler_option_id' => $settings['samplerOptionId'],
            'scheduler_option_id' => $settings['schedulerOptionId'],
            'created_at' => now(),
            'updated_at' => now(),
        ]))->all());
    }
}
