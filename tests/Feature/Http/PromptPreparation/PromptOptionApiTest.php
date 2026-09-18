<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Tests\TestCase;

class PromptOptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_オプションを整形して登録し名前とIDの順で取得する(): void
    {
        $second = $this->create('B 精密', "sharp focus\nintricate");
        $first = $this->create('A 高精細', 'detailed, sharp focus');
        $sameName = $this->create('A 高精細', '(detailed)');

        $this->getJson(route('prompt-options.index'))->assertExactJson([
            'options' => [
                ['id' => $first, 'name' => 'A 高精細', 'content' => 'detailed, sharp focus,'],
                ['id' => $sameName, 'name' => 'A 高精細', 'content' => '(detailed),'],
                ['id' => $second, 'name' => 'B 精密', 'content' => 'sharp focus, intricate,'],
            ],
        ]);
    }

    public function test_オプションを編集して削除する(): void
    {
        $id = $this->create('変更前', 'before');

        $this->putJson(route('prompt-options.update', ['option' => $id]), [
            'name' => '変更後',
            'content' => "after\nvalue",
        ])->assertOk()->assertJson([
            'id' => $id,
            'name' => '変更後',
            'content' => 'after, value,',
        ]);
        $this->assertDatabaseHas('option_prompts', [
            'id' => $id,
            'name' => '変更後',
            'content' => 'after, value,',
        ]);

        $this->deleteJson(route('prompt-options.destroy', ['option' => $id]))->assertNoContent();
        $this->assertDatabaseMissing('option_prompts', ['id' => $id]);
    }

    public function test_空の登録名と文面はオプションへ保存できない(): void
    {
        $this->postJson(route('prompt-options.store'), ['name' => ' ', 'content' => 'valid'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('prompt-options.store'), ['name' => '登録名', 'content' => " ,\n,"])
            ->assertUnprocessable()->assertJsonValidationErrors('content');

        $this->assertDatabaseCount('option_prompts', 0);
    }

    public function test_存在しないオプションは編集も削除もできない(): void
    {
        $this->putJson(route('prompt-options.update', ['option' => 999999]), [
            'name' => '存在しない候補',
            'content' => 'missing',
        ])->assertNotFound();
        $this->deleteJson(route('prompt-options.destroy', ['option' => 999999]))->assertNotFound();
    }

    public function test_同じ登録名と文面のオプションを複数登録できる(): void
    {
        $first = $this->create('高精細', 'detailed');
        $second = $this->create('高精細', 'detailed');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('option_prompts', 2);
        $this->getJson(route('prompt-options.index'))->assertJsonPath('options', [
            ['id' => $first, 'name' => '高精細', 'content' => 'detailed,'],
            ['id' => $second, 'name' => '高精細', 'content' => 'detailed,'],
        ]);
    }

    public function test_オプションの更新ルートにCSRF保護を適用する(): void
    {
        $router = app(Router::class);
        $middlewareGroups = app(HttpKernel::class)->getMiddlewareGroups();

        foreach (['prompt-options.store', 'prompt-options.update', 'prompt-options.destroy'] as $routeName) {
            $route = $router->getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('web', $route->middleware());
        }
        $this->assertContains(PreventRequestForgery::class, $middlewareGroups['web']);
    }

    private function create(string $name, string $content): int
    {
        return (int) $this->postJson(route('prompt-options.store'), [
            'name' => $name,
            'content' => $content,
        ])->assertCreated()->json('id');
    }
}
