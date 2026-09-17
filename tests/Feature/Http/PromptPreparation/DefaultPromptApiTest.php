<?php

namespace Tests\Feature\Http\PromptPreparation;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Tests\TestCase;

class DefaultPromptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_Seederで登録したpositiveとnegativeを取得する(): void
    {
        $positive = 'masterpiece, best quality, highres,';
        $negative = 'bad anatomy, bad hands, extra fingers, missing fingers, extra limbs,';
        $this->seed(DatabaseSeeder::class);

        $response = $this->getJson(route('default-prompts.index'));

        $response
            ->assertOk()
            ->assertExactJson([
                'positive' => $positive,
                'negative' => $negative,
            ]);
    }

    public function test_positiveを整形して保存しnegativeは変更しない(): void
    {
        $input = "abc\ndef,,abc";
        $expectedPositive = 'abc, def,';
        $expectedNegative = 'negative,';
        $this->insertDefaultPrompts('positive,', $expectedNegative);

        $response = $this->putJson(route('default-prompts.update', ['polarity' => 'positive']), [
            'content' => $input,
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'polarity' => 'positive',
                'content' => $expectedPositive,
                'formatSucceeded' => true,
            ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => 'positive',
            'content' => $expectedPositive,
        ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => 'negative',
            'content' => $expectedNegative,
        ]);
    }

    public function test_negativeを整形して保存しpositiveは変更しない(): void
    {
        $input = "bad anatomy\nbad hands,,bad anatomy";
        $expectedPositive = 'masterpiece,';
        $expectedNegative = 'bad anatomy, bad hands,';
        $this->insertDefaultPrompts($expectedPositive, 'negative,');

        $response = $this->putJson(route('default-prompts.update', ['polarity' => 'negative']), [
            'content' => $input,
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'polarity' => 'negative',
                'content' => $expectedNegative,
                'formatSucceeded' => true,
            ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => 'positive',
            'content' => $expectedPositive,
        ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => 'negative',
            'content' => $expectedNegative,
        ]);
    }

    public function test_空文字列を保存してデフォルト文面を削除する(): void
    {
        $this->insertDefaultPrompts('positive,', 'negative,');

        $response = $this->putJson(route('default-prompts.update', ['polarity' => 'positive']), [
            'content' => '',
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'polarity' => 'positive',
                'content' => '',
                'formatSucceeded' => true,
            ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => 'positive',
            'content' => '',
        ]);
    }

    public function test_対応しない丸括弧を含む文面は原文を保存する(): void
    {
        $input = '  (abc,def  ';

        $response = $this->putJson(route('default-prompts.update', ['polarity' => 'positive']), [
            'content' => $input,
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'polarity' => 'positive',
                'content' => $input,
                'formatSucceeded' => false,
            ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => 'positive',
            'content' => $input,
        ]);
    }

    public function test_contentが未指定またはnullの場合は保存しない(): void
    {
        $missingResponse = $this->putJson(route('default-prompts.update', ['polarity' => 'positive']), []);
        $invalidResponse = $this->putJson(route('default-prompts.update', ['polarity' => 'positive']), [
            'content' => null,
        ]);

        $missingResponse->assertUnprocessable()->assertJsonValidationErrors('content');
        $invalidResponse->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->assertDatabaseMissing('default_prompts', ['polarity' => 'positive']);
    }

    public function test_contentが文字列以外の場合は保存しない(): void
    {
        $input = ['abc'];

        $response = $this->putJson(route('default-prompts.update', ['polarity' => 'positive']), [
            'content' => $input,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->assertDatabaseMissing('default_prompts', ['polarity' => 'positive']);
    }

    public function test_定義されていない種別は保存しない(): void
    {
        $response = $this->putJson(route('default-prompts.update', ['polarity' => 'unknown']), []);

        $response->assertNotFound();
        $this->assertDatabaseCount('default_prompts', 0);
    }

    public function test_保存ルートにCSRF保護を適用する(): void
    {
        $router = app(Router::class);
        $route = $router->getRoutes()->getByName('default-prompts.update');
        $middlewareGroups = app(HttpKernel::class)->getMiddlewareGroups();

        $this->assertNotNull($route);
        $this->assertContains('web', $route->middleware());
        $this->assertContains(
            PreventRequestForgery::class,
            $middlewareGroups['web'],
        );
    }

    private function insertDefaultPrompts(string $positive, string $negative): void
    {
        $now = now();

        $this->app['db']->table('default_prompts')->insert([
            [
                'polarity' => 'positive',
                'content' => $positive,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'polarity' => 'negative',
                'content' => $negative,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
