<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

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
}
