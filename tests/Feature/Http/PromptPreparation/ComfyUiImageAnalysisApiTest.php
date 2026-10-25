<?php

namespace Tests\Feature\Http\PromptPreparation;

use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ComfyUiImageAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_登録ワークフローと一致するPNGから両プロンプトを解析する(): void
    {
        Storage::fake('local');
        $this->seed();
        $workflow = $this->workflow('registered positive,', 'registered negative,', 1);
        $this->app->make(ComfyUiWorkflowStore::class)->save(1, 'fictional.json', json_encode($workflow, JSON_THROW_ON_ERROR), $this->mappings());
        $before = Storage::disk('local')->allFiles();
        $imageWorkflow = $this->workflow('image positive,', 'image negative,', 999);

        $response = $this->postJson(route('comfyui-images.analyze'), [
            'image' => UploadedFile::fake()->createWithContent('fictional.png', $this->png($imageWorkflow)),
        ]);

        $response->assertOk()
            ->assertJsonPath('modelFamily.id', 1)
            ->assertJsonPath('positivePrompt', 'image positive,')
            ->assertJsonPath('negativePrompt', 'image negative,');
        self::assertSame($before, Storage::disk('local')->allFiles());
    }

    public function test_PNG以外とpromptメタデータがないPNGを拒否する(): void
    {
        $this->postJson(route('comfyui-images.analyze'), [
            'image' => UploadedFile::fake()->create('fictional.txt', 1, 'text/plain'),
        ])->assertUnprocessable();

        $this->postJson(route('comfyui-images.analyze'), [
            'image' => UploadedFile::fake()->createWithContent('empty.png', "\x89PNG\r\n\x1a\n".$this->chunk('IEND', '')),
        ])->assertUnprocessable()->assertJsonStructure(['message']);
    }

    public function test_20MiBを超えるPNGを拒否する(): void
    {
        $this->postJson(route('comfyui-images.analyze'), [
            'image' => UploadedFile::fake()->create('large.png', 20481, 'image/png'),
        ])->assertUnprocessable();
    }

    /** @return array<string, mixed> */
    private function workflow(string $positive, string $negative, int $seed): array
    {
        return [
            '1' => ['inputs' => ['text' => $positive], 'class_type' => 'Text'],
            '2' => ['inputs' => ['text' => $negative], 'class_type' => 'Text'],
            '3' => ['inputs' => ['seed' => $seed, 'fixed' => 'same'], 'class_type' => 'Sampler'],
        ];
    }

    /** @return array<string, mixed> */
    private function mappings(): array
    {
        return [
            'positive' => ['nodeId' => '1', 'inputName' => 'text'],
            'negative' => ['nodeId' => '2', 'inputName' => 'text'],
            'seed' => ['nodeId' => '3', 'inputName' => 'seed'],
            'checkpoint' => [], 'sampler' => [], 'scheduler' => [], 'outputFilenamePrefix' => [],
        ];
    }

    /** @param array<string, mixed> $workflow */
    private function png(array $workflow): string
    {
        return "\x89PNG\r\n\x1a\n".$this->chunk('tEXt', "prompt\0".json_encode($workflow, JSON_THROW_ON_ERROR)).$this->chunk('IEND', '');
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
