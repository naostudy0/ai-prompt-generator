<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Infrastructure\PromptPreparation\External\IllustrationLlm\OllamaIllustrationPromptGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OllamaIllustrationPromptGeneratorTest extends TestCase
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

    public function test_構造化形式を指定して生成結果と安全なメトリクスを返す(): void
    {
        $loggedMessage = null;
        $loggedContext = null;
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) use (&$loggedMessage, &$loggedContext): bool {
            $loggedMessage = $message;
            $loggedContext = $context;

            return true;
        });
        Http::fake([
            'http://llm.example.test/api/chat' => Http::response([
                'model' => 'resolved-model',
                'message' => ['content' => json_encode([
                    'positive_prompt' => '  1girl, blue eyes  ',
                    'negative_prompt' => '   ',
                    'reusable_tag_groups' => [
                        [
                            'label' => ' 黒髪ロングの女の子 ',
                            'tags' => ' 1girl, solo, long black hair ',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)],
                'total_duration' => 2_000_000_000,
                'eval_duration' => 500_000_000,
                'eval_count' => 25,
                'prompt_eval_count' => 12,
            ]),
        ]);

        $result = app(OllamaIllustrationPromptGenerator::class)->generate(
            'SYSTEM INSTRUCTION SECRET',
            'USER DESCRIPTION SECRET',
        );

        self::assertSame('1girl, blue eyes', $result->positivePrompt);
        self::assertSame('', $result->negativePrompt);
        self::assertSame('resolved-model', $result->model);
        self::assertSame('黒髪ロングの女の子', $result->reusableTagGroups[0]->label);
        self::assertSame('1girl, solo, long black hair', $result->reusableTagGroups[0]->tags);
        self::assertGreaterThanOrEqual(0, $result->generationTimeMs);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'http://llm.example.test/api/chat'
                && $data['model'] === 'illustration-model'
                && $data['stream'] === false
                && $data['think'] === false
                && $data['options'] === ['num_predict' => 512]
                && $data['messages'] === [
                    ['role' => 'system', 'content' => 'SYSTEM INSTRUCTION SECRET'],
                    ['role' => 'user', 'content' => 'USER DESCRIPTION SECRET'],
                ]
                && $data['format']['type'] === 'object'
                && $data['format']['required'] === [
                    'positive_prompt',
                    'negative_prompt',
                    'reusable_tag_groups',
                ]
                && $data['format']['properties']['reusable_tag_groups']['minItems'] === 1
                && $data['format']['properties']['reusable_tag_groups']['maxItems'] === 10
                && $data['format']['properties']['reusable_tag_groups']['items']['required'] === [
                    'label',
                    'tags',
                ];
        });
        self::assertSame('Illustration prompt generation completed.', $loggedMessage);
        self::assertIsArray($loggedContext);
        self::assertIsString($loggedContext['request_id']);
        self::assertSame('success', $loggedContext['outcome']);
        self::assertSame('resolved-model', $loggedContext['model']);
        self::assertIsInt($loggedContext['elapsed_ms']);
        self::assertEquals(2000.0, $loggedContext['total_duration_ms']);
        self::assertSame(25, $loggedContext['eval_count']);
        self::assertSame(12, $loggedContext['prompt_eval_count']);
        self::assertEquals(50.0, $loggedContext['tokens_per_second']);
        $encoded = json_encode($loggedContext, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('SECRET', $encoded);
        self::assertStringNotContainsString('1girl', $encoded);
        self::assertStringNotContainsString('黒髪ロングの女の子', $encoded);
    }

    #[DataProvider('unavailableResponses')]
    public function test_http失敗はサービス利用不可として扱う(int $status): void
    {
        Http::fake(['*' => Http::response('RAW SECRET RESPONSE', $status)]);

        try {
            app(OllamaIllustrationPromptGenerator::class)->generate('system', 'description');
            self::fail('Exception was not thrown.');
        } catch (IllustrationPromptServiceUnavailable $exception) {
            self::assertStringNotContainsString('RAW SECRET RESPONSE', $exception->getMessage());
            self::assertStringNotContainsString('llm.example.test', $exception->getMessage());
        }
    }

    /** @return array<string, array{int}> */
    public static function unavailableResponses(): array
    {
        return ['client error' => [400], 'server error' => [500], 'redirect' => [302]];
    }

    #[DataProvider('connectionFailures')]
    public function test_接続失敗とタイムアウトはサービス利用不可として扱う(string $message): void
    {
        Http::fake(fn () => throw new ConnectionException($message));

        $this->expectException(IllustrationPromptServiceUnavailable::class);
        $this->expectExceptionMessage('外部LLMを利用できません。');

        app(OllamaIllustrationPromptGenerator::class)->generate('system', 'description');
    }

    /** @return array<string, array{string}> */
    public static function connectionFailures(): array
    {
        return ['connection' => ['could not connect'], 'timeout' => ['operation timed out']];
    }

    #[DataProvider('invalidResponses')]
    public function test_不正な応答を拒否する(mixed $body): void
    {
        Http::fake(['*' => Http::response($body)]);

        try {
            app(OllamaIllustrationPromptGenerator::class)->generate('system', 'description');
            self::fail('Exception was not thrown.');
        } catch (InvalidIllustrationPromptResponse $exception) {
            self::assertStringNotContainsString('RAW SECRET', $exception->getMessage());
            self::assertStringNotContainsString('llm.example.test', $exception->getMessage());
        }
    }

    /** @return array<string, array{mixed}> */
    public static function invalidResponses(): array
    {
        return [
            'non-object outer JSON' => ['RAW SECRET'],
            'missing content' => [['message' => []]],
            'invalid content JSON' => [['message' => ['content' => 'RAW SECRET {']]],
            'missing key' => [['message' => ['content' => '{"positive_prompt":"ok"}']]],
            'wrong type' => [['message' => ['content' => '{"positive_prompt":1,"negative_prompt":""}']]],
            'empty positive' => [['message' => ['content' => '{"positive_prompt":" ","negative_prompt":""}']]],
            'missing reusable groups' => [['message' => ['content' => '{"positive_prompt":"ok","negative_prompt":""}']]],
            'empty reusable groups' => [['message' => ['content' => '{"positive_prompt":"ok","negative_prompt":"","reusable_tag_groups":[]}']]],
            'too many reusable groups' => [['message' => ['content' => json_encode([
                'positive_prompt' => 'ok',
                'negative_prompt' => '',
                'reusable_tag_groups' => array_fill(0, 11, ['label' => '場面', 'tags' => 'sunset']),
            ], JSON_THROW_ON_ERROR)]]],
            'reusable groups is not a list' => [['message' => ['content' => '{"positive_prompt":"ok","negative_prompt":"","reusable_tag_groups":{"label":"場面","tags":"sunset"}}']]],
            'reusable group is not an object' => [['message' => ['content' => '{"positive_prompt":"ok","negative_prompt":"","reusable_tag_groups":[[]]}']]],
            'empty reusable label' => [['message' => ['content' => '{"positive_prompt":"ok","negative_prompt":"","reusable_tag_groups":[{"label":"　","tags":"sunset"}]}']]],
            'long reusable label' => [['message' => ['content' => json_encode([
                'positive_prompt' => 'ok',
                'negative_prompt' => '',
                'reusable_tag_groups' => [[
                    'label' => str_repeat('あ', 101),
                    'tags' => 'sunset',
                ]],
            ], JSON_THROW_ON_ERROR)]]],
            'reusable tags wrong type' => [['message' => ['content' => '{"positive_prompt":"ok","negative_prompt":"","reusable_tag_groups":[{"label":"場面","tags":1}]}']]],
            'reusable tags only separators' => [['message' => ['content' => '{"positive_prompt":"ok","negative_prompt":"","reusable_tag_groups":[{"label":"場面","tags":" ,　, "}]}']]],
        ];
    }

    /** @param array<string, int> $metrics */
    #[DataProvider('optionalMetrics')]
    public function test_評価時間がないかゼロなら毎秒トークン数を記録しない(array $metrics): void
    {
        $loggedContext = null;
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) use (&$loggedContext): bool {
            $loggedContext = $context;

            return true;
        });
        Http::fake(['*' => Http::response(array_merge([
            'model' => 'model',
            'message' => ['content' => '{"positive_prompt":"ok","negative_prompt":"","reusable_tag_groups":[{"label":"場面","tags":"sunset"}]}'],
        ], $metrics))]);

        app(OllamaIllustrationPromptGenerator::class)->generate('system', 'description');

        self::assertIsArray($loggedContext);
        self::assertArrayNotHasKey('tokens_per_second', $loggedContext);
    }

    /** @return array<string, array{array<string, int>}> */
    public static function optionalMetrics(): array
    {
        return ['absent' => [[]], 'zero duration' => [['eval_duration' => 0, 'eval_count' => 20]]];
    }
}
