<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FavoritePromptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_完成プロンプトと選択内容と画像を保存して取得する(): void
    {
        Storage::fake('public');
        $snapshot = $this->validSnapshot([3]);
        $summary = [[
            'key' => 'default',
            'label' => 'デフォルト',
            'items' => [['label' => 'positive デフォルト', 'meta' => '', 'details' => []]],
        ]];

        $response = $this->post(route('favorite-prompts.store'), [
            'name' => '公園の構図',
            'positivePrompt' => 'masterpiece, park,',
            'negativePrompt' => 'bad anatomy,',
            'selectionSnapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'selectionSummary' => json_encode($summary, JSON_THROW_ON_ERROR),
            'image' => UploadedFile::fake()->createWithContent(
                'sample.png',
                $this->pngContent(),
            ),
        ]);

        $id = $response->assertCreated()->json('id');
        $this->getJson(route('favorite-prompts.show', ['favoritePrompt' => $id]))
            ->assertOk()
            ->assertJsonPath('name', '公園の構図')
            ->assertJsonPath('positivePrompt', 'masterpiece, park,')
            ->assertJsonPath('selectionSnapshot.optionIds.0', 3)
            ->assertJsonPath('selectionSummary.0.items.0.label', 'positive デフォルト')
            ->assertJson(fn ($json) => $json->whereType('imageUrl', 'string')->etc());

        $path = $this->getStoredImagePath($id);
        Storage::disk('public')->assertExists($path);
    }

    public function test_名前なしのお気に入りを日時名で一覧表示して上書きと削除を行う(): void
    {
        Storage::fake('public');
        $payload = [
            'name' => '',
            'positivePrompt' => 'masterpiece,',
            'negativePrompt' => '',
            'selectionSnapshot' => json_encode($this->validSnapshot(), JSON_THROW_ON_ERROR),
            'selectionSummary' => json_encode([], JSON_THROW_ON_ERROR),
        ];
        $id = $this->post(route('favorite-prompts.store'), $payload)->assertCreated()->json('id');

        $this->getJson(route('favorite-prompts.index'))
            ->assertOk()
            ->assertJsonPath('favorites.0.id', $id)
            ->assertJsonPath('favorites.0.name', null)
            ->assertJsonPath('favorites.0.displayName', now()->format('Y/m/d H:i').'のお気に入り');

        $payload['positivePrompt'] = 'masterpiece, sunset,';
        $this->put(route('favorite-prompts.update', ['favoritePrompt' => $id]), $payload)
            ->assertOk()
            ->assertJsonPath('id', $id);
        $this->getJson(route('favorite-prompts.show', ['favoritePrompt' => $id]))
            ->assertJsonPath('positivePrompt', 'masterpiece, sunset,');

        $this->delete(route('favorite-prompts.destroy', ['favoritePrompt' => $id]))->assertNoContent();
        $this->getJson(route('favorite-prompts.show', ['favoritePrompt' => $id]))->assertNotFound();
    }

    public function test_空のプロンプトと許可されない画像は保存しない(): void
    {
        Storage::fake('public');
        $base = [
            'selectionSnapshot' => json_encode($this->validSnapshot(), JSON_THROW_ON_ERROR),
            'selectionSummary' => json_encode([], JSON_THROW_ON_ERROR),
        ];

        $this->postJson(route('favorite-prompts.store'), [
            ...$base,
            'positivePrompt' => '',
            'negativePrompt' => '',
        ])->assertUnprocessable();

        $this->withHeader('Accept', 'application/json')->post(route('favorite-prompts.store'), [
            ...$base,
            'positivePrompt' => 'masterpiece,',
            'negativePrompt' => '',
            'image' => UploadedFile::fake()->create('sample.gif', 10, 'image/gif'),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('favorite_prompts', 0);
    }

    public function test_不正な選択構造と存在しない更新対象は保存しない(): void
    {
        $payload = [
            'positivePrompt' => 'masterpiece,',
            'negativePrompt' => '',
            'selectionSnapshot' => json_encode([
                ...$this->validSnapshot(),
                'lora' => ['loraId' => 1, 'strength' => 1.5, 'triggerId' => null, 'outfitId' => null],
            ], JSON_THROW_ON_ERROR),
            'selectionSummary' => json_encode(['不正'], JSON_THROW_ON_ERROR),
        ];

        $this->postJson(route('favorite-prompts.store'), $payload)->assertUnprocessable();

        $payload['selectionSnapshot'] = json_encode($this->validSnapshot(), JSON_THROW_ON_ERROR);
        $payload['selectionSummary'] = json_encode([], JSON_THROW_ON_ERROR);
        $this->putJson(route('favorite-prompts.update', ['favoritePrompt' => 999]), $payload)
            ->assertNotFound();
    }

    private function getStoredImagePath(int $id): string
    {
        return (string) DB::table('favorite_prompts')->where('id', $id)->value('image_path');
    }

    private function pngContent(): string
    {
        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($content);

        return $content;
    }

    /**
     * @param  list<int>  $optionIds
     * @return array<string, mixed>
     */
    private function validSnapshot(array $optionIds = []): array
    {
        return [
            'defaults' => ['positive' => true, 'negative' => true],
            'lora' => ['loraId' => null, 'strength' => 1, 'triggerId' => null, 'outfitId' => null],
            'clothingLoras' => [],
            'optionIds' => $optionIds,
        ];
    }
}
