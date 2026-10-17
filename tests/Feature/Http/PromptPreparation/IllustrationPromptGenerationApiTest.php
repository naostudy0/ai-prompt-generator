<?php

namespace Tests\Feature\Http\PromptPreparation;

use App\Application\PromptPreparation\DTO\GeneratedIllustrationPrompt;
use App\Application\PromptPreparation\Exceptions\IllustrationPromptServiceUnavailable;
use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Ports\IllustrationPromptGenerator;
use App\Application\PromptPreparation\Ports\IllustrationPromptInstructions;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class IllustrationPromptGenerationApiTest extends TestCase
{
    public function test_既定スタイルで生成結果をcamel_caseのjsonとして返す(): void
    {
        $this->bindSuccessFakes();

        $this->postJson(route('illustration-prompts.generate'), ['description' => '  青い目  '])
            ->assertOk()
            ->assertExactJson([
                'positivePrompt' => 'positive',
                'negativePrompt' => 'negative',
                'model' => 'fake-model',
                'promptStyle' => 'danbooru_tags',
                'generationTimeMs' => 12,
            ]);
    }

    /** @param array<string, string> $payload */
    #[DataProvider('validInputs')]
    public function test_有効な説明とスタイルを受け付ける(array $payload, string $expectedDescription, string $expectedStyle): void
    {
        $received = [];
        $this->bindSuccessFakes($received);

        $this->postJson(route('illustration-prompts.generate'), $payload)->assertOk();

        self::assertSame($expectedDescription, $received['description']);
        self::assertSame($expectedStyle, $received['instruction']);
    }

    /** @return array<string, array{array<string, string>, string, string}> */
    public static function validInputs(): array
    {
        return [
            '2000 characters' => [['description' => str_repeat('あ', 2000)], str_repeat('あ', 2000), 'danbooru_tags'],
            'danbooru' => [['description' => '説明', 'promptStyle' => 'danbooru_tags'], '説明', 'danbooru_tags'],
            'natural' => [['description' => '説明', 'promptStyle' => 'natural_language'], '説明', 'natural_language'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidInputs')]
    public function test_不正な入力を422で拒否する(array $payload): void
    {
        $this->bindSuccessFakes();

        $this->postJson(route('illustration-prompts.generate'), $payload)->assertUnprocessable();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidInputs(): array
    {
        return [
            'missing' => [[]],
            'null' => [['description' => null]],
            'array' => [['description' => ['text']]],
            'empty' => [['description' => '']],
            'ascii whitespace' => [['description' => " \t\n"]],
            'unicode whitespace' => [['description' => "\u{3000}\u{00A0}"]],
            'too long' => [['description' => str_repeat('あ', 2001)]],
            'invalid style' => [['description' => '説明', 'promptStyle' => 'unknown']],
            'array style' => [['description' => '説明', 'promptStyle' => []]],
        ];
    }

    #[DataProvider('mappedFailures')]
    public function test_外部サービスの失敗を分類したstatusへ変換する(string $exceptionClass, int $status): void
    {
        $exception = new $exceptionClass('RAW SECRET http://upstream.test');
        self::assertInstanceOf(RuntimeException::class, $exception);
        $this->bindThrowingGenerator($exception);

        $response = $this->postJson(route('illustration-prompts.generate'), ['description' => '説明']);

        $response->assertStatus($status);
        self::assertStringNotContainsString('RAW SECRET', (string) $response->getContent());
        self::assertStringNotContainsString('upstream.test', (string) $response->getContent());
    }

    /** @return array<string, array{class-string<RuntimeException>, int}> */
    public static function mappedFailures(): array
    {
        return [
            'unavailable' => [IllustrationPromptServiceUnavailable::class, 503],
            'invalid response' => [InvalidIllustrationPromptResponse::class, 502],
        ];
    }

    public function test_予期しない例外は内部詳細なしの500を返す(): void
    {
        config()->set('app.debug', false);
        $this->bindThrowingGenerator(new RuntimeException('INTERNAL SECRET'));

        $response = $this->postJson(route('illustration-prompts.generate'), ['description' => '説明']);

        $response->assertInternalServerError();
        self::assertStringNotContainsString('INTERNAL SECRET', (string) $response->getContent());
    }

    public function test_webミドルウェアとcsrf保護を使用する(): void
    {
        $route = Route::getRoutes()->getByName('illustration-prompts.generate');

        self::assertNotNull($route);
        self::assertContains('web', $route->gatherMiddleware());
    }

    /** @param array<string, string> $received */
    private function bindSuccessFakes(array &$received = []): void
    {
        $this->app->instance(IllustrationPromptInstructions::class, new class () implements IllustrationPromptInstructions {
            public function for(PromptStyle $style): string
            {
                return $style->value;
            }
        });
        $capture = static function (string $systemPrompt, string $description) use (&$received): void {
            $received = ['instruction' => $systemPrompt, 'description' => $description];
        };
        $this->app->instance(IllustrationPromptGenerator::class, new class ($capture) implements IllustrationPromptGenerator {
            public function __construct(private readonly \Closure $capture)
            {
            }

            public function generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt
            {
                ($this->capture)($systemPrompt, $description);

                return new GeneratedIllustrationPrompt('positive', 'negative', 'fake-model', 12);
            }
        });
    }

    private function bindThrowingGenerator(RuntimeException $exception): void
    {
        $this->app->instance(IllustrationPromptInstructions::class, new class () implements IllustrationPromptInstructions {
            public function for(PromptStyle $style): string
            {
                return 'instruction';
            }
        });
        $this->app->instance(IllustrationPromptGenerator::class, new class ($exception) implements IllustrationPromptGenerator {
            public function __construct(private readonly RuntimeException $exception)
            {
            }

            public function generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt
            {
                throw $this->exception;
            }
        });
    }
}
