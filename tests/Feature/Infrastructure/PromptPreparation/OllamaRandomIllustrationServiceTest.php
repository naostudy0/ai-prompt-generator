<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Infrastructure\PromptPreparation\External\IllustrationLlm\OllamaRandomIllustrationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OllamaRandomIllustrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.illustration_llm', [
            'base_url' => 'http://llm.example.test/',
            'model' => 'illustration-model',
            'connect_timeout' => 2,
            'timeout' => 30,
            'num_predict' => 512,
        ]);
    }

    public function test_temperatureを0にして構造化されたランダム案を要求する(): void
    {
        Http::fake([
            'http://llm.example.test/api/chat' => Http::response([
                'model' => 'illustration-model',
                'message' => ['content' => '{"description":"架空の図書館を描いた一枚絵。"}'],
            ]),
        ]);

        app(OllamaRandomIllustrationService::class)->generate('system', [], null);

        Http::assertSent(static function (Request $request): bool {
            $data = $request->data();

            return $data['options'] === [
                'num_predict' => 512,
                'temperature' => 0,
            ];
        });
    }

    public function test_反映解析の文字数と件数をJSONSchemaで制限する(): void
    {
        Http::fake(['*' => Http::response($this->resolvedResponse())]);

        app(OllamaRandomIllustrationService::class)->resolve('system', '架空の説明', [], []);

        Http::assertSent(static function (Request $request): bool {
            $schema = $request->data()['format']['properties'];
            $suggestion = $schema['transient_suggestions']['items']['properties'];

            return $schema['resolved_description']['maxLength'] === 2000
                && $suggestion['label']['maxLength'] === 100
                && $suggestion['tags']['maxLength'] === 500
                && $schema['adjustments']['maxItems'] === 10
                && $schema['adjustments']['items']['maxLength'] === 500;
        });
    }

    public function test_上限を超える一時候補タグを拒否する(): void
    {
        Http::fake(['*' => Http::response($this->resolvedResponse([
            'polarity' => 'positive',
            'label' => '架空の光',
            'tags' => str_repeat('a', 501),
        ]))]);

        $this->expectException(InvalidIllustrationPromptResponse::class);

        app(OllamaRandomIllustrationService::class)->resolve('system', '架空の説明', [], []);
    }

    /** @param array{polarity:string,label:string,tags:string}|null $suggestion
     *  @return array{model:string,message:array{content:string}}
     */
    private function resolvedResponse(?array $suggestion = null): array
    {
        return [
            'model' => 'illustration-model',
            'message' => ['content' => json_encode([
                'resolved_description' => '架空の説明',
                'selected_option_ids' => [],
                'transient_suggestions' => $suggestion === null ? [] : [$suggestion],
                'adjustments' => [],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        ];
    }
}
