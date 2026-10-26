<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RandomIllustrationIdeaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_日本語案を生成して編集後の案を解析する(): void
    {
        Http::fakeSequence()
            ->push(['model' => 'fake-model', 'message' => ['content' => json_encode(['description' => '雨上がりの図書館で、窓から柔らかな光が差す静かな一枚絵。'], JSON_UNESCAPED_UNICODE)]])
            ->push(['model' => 'fake-model', 'message' => ['content' => json_encode(['resolved_description' => '雨上がりの図書館で、窓から柔らかな光が差す静かな一枚絵。', 'selected_option_ids' => [], 'transient_suggestions' => [['polarity' => 'positive', 'label' => '雨上がりの光', 'tags' => 'after rain, soft light,']], 'adjustments' => []], JSON_UNESCAPED_UNICODE)]]);

        $generated = $this->postJson('/random-illustration-ideas', ['fixedSelections' => $this->fixedSelections()])
            ->assertOk()->assertJsonPath('model', 'fake-model')->assertJsonPath('locationAnchor', null);
        $ideaId = $generated->json('ideaId');
        self::assertIsString($ideaId);

        $this->postJson('/random-illustration-ideas/resolve', ['ideaId' => $ideaId, 'description' => ' 雨上がりの図書館で、窓から柔らかな光が差す静かな一枚絵。 ', 'fixedSelections' => $this->fixedSelections()])
            ->assertOk()
            ->assertJsonPath('ideaId', $ideaId)
            ->assertJsonPath('suggestions.0.polarity', 'positive')
            ->assertJsonPath('suggestions.0.label', '雨上がりの光');
    }

    public function test_空の説明と信用できない選択形状を拒否する(): void
    {
        $this->postJson('/random-illustration-ideas/resolve', ['ideaId' => fake()->uuid(), 'description' => '  ', 'fixedSelections' => $this->fixedSelections()])
            ->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->postJson('/random-illustration-ideas', ['fixedSelections' => ['optionIds' => ['not-an-id']]])
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    /** @return array<string, mixed> */
    private function fixedSelections(): array
    {
        return [
            'positiveDefault' => false, 'negativeDefault' => false, 'modelFamilyId' => 1,
            'character' => ['loraId' => null, 'strength' => 1, 'triggerId' => null, 'outfitId' => null],
            'clothingLoras' => [], 'optionIds' => [],
        ];
    }
}
