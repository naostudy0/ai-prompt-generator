<?php

namespace Tests\Feature\Http\PromptPreparation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_表情と視線を整形して登録し一覧取得する(): void
    {
        $expression = $this->postJson(route('expressions.store'), [
            'name' => '口を開けた笑顔',
            'content' => "smile\nopen mouth,,smile",
        ])->assertCreated();
        $gaze = $this->postJson(route('gazes.store'), [
            'name' => 'カメラ目線',
            'content' => 'looking at viewer',
        ])->assertCreated();

        $this->getJson(route('character-directions.index'))->assertExactJson([
            'expressions' => [[
                'id' => $expression->json('id'),
                'name' => '口を開けた笑顔',
                'content' => 'smile, open mouth,',
            ]],
            'gazes' => [[
                'id' => $gaze->json('id'),
                'name' => 'カメラ目線',
                'content' => 'looking at viewer,',
            ]],
        ]);
    }

    public function test_場所と構図と動作を登録し名前順で一覧取得する(): void
    {
        $bench = $this->create('locations.store', 'B ベンチ', 'bench');
        $park = $this->create('locations.store', 'A 公園', 'park');
        $composition = $this->create('compositions.store', '正面', 'from front');
        $action = $this->create('actions.store', '座る', 'sitting');

        $this->getJson(route('scene-directions.index'))->assertExactJson([
            'locations' => [
                ['id' => $park, 'name' => 'A 公園', 'content' => 'park,'],
                ['id' => $bench, 'name' => 'B ベンチ', 'content' => 'bench,'],
            ],
            'compositions' => [
                ['id' => $composition, 'name' => '正面', 'content' => 'from front,'],
            ],
            'actions' => [
                ['id' => $action, 'name' => '座る', 'content' => 'sitting,'],
            ],
        ]);
    }

    public function test_各カテゴリの候補を編集して削除する(): void
    {
        foreach ($this->categories() as [$storeRoute, $updateRoute, $destroyRoute, $parameter, $table]) {
            $id = $this->create($storeRoute, '変更前', 'before');

            $this->putJson(route($updateRoute, [$parameter => $id]), [
                'name' => '変更後',
                'content' => "after\nvalue",
            ])->assertOk()->assertJson([
                'id' => $id,
                'name' => '変更後',
                'content' => 'after, value,',
            ]);
            $this->assertDatabaseHas($table, [
                'id' => $id,
                'name' => '変更後',
                'content' => 'after, value,',
            ]);

            $this->deleteJson(route($destroyRoute, [$parameter => $id]))->assertNoContent();
            $this->assertDatabaseMissing($table, ['id' => $id]);
        }
    }

    public function test_空の登録名と文面は各カテゴリへ保存できない(): void
    {
        foreach ($this->categories() as [$storeRoute]) {
            $this->postJson(route($storeRoute), ['name' => ' ', 'content' => 'valid'])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
            $this->postJson(route($storeRoute), ['name' => '登録名', 'content' => " ,\n,"])
                ->assertUnprocessable()->assertJsonValidationErrors('content');
            $this->assertDatabaseCount($this->tableForRoute($storeRoute), 0);
        }
    }

    public function test_同じ登録名と文面を持つ候補を登録しID順で取得する(): void
    {
        $firstId = $this->create('locations.store', '公園', 'park');
        $secondId = $this->create('locations.store', '公園', 'park');

        $this->getJson(route('scene-directions.index'))->assertJsonPath('locations', [
            ['id' => $firstId, 'name' => '公園', 'content' => 'park,'],
            ['id' => $secondId, 'name' => '公園', 'content' => 'park,'],
        ]);
    }

    public function test_存在しない候補は編集も削除もできない(): void
    {
        foreach ($this->categories() as [, $updateRoute, $destroyRoute, $parameter]) {
            $this->putJson(route($updateRoute, [$parameter => 999999]), [
                'name' => '存在しない候補',
                'content' => 'missing',
            ])->assertNotFound();
            $this->deleteJson(route($destroyRoute, [$parameter => 999999]))->assertNotFound();
        }
    }

    /** @return list<array{string, string, string, string, string}> */
    private function categories(): array
    {
        return [
            ['expressions.store', 'expressions.update', 'expressions.destroy', 'expression', 'expression_prompts'],
            ['gazes.store', 'gazes.update', 'gazes.destroy', 'gaze', 'gaze_prompts'],
            ['locations.store', 'locations.update', 'locations.destroy', 'location', 'location_prompts'],
            ['compositions.store', 'compositions.update', 'compositions.destroy', 'composition', 'composition_prompts'],
            ['actions.store', 'actions.update', 'actions.destroy', 'action', 'action_prompts'],
        ];
    }

    private function create(string $routeName, string $name, string $content): int
    {
        return (int) $this->postJson(route($routeName), [
            'name' => $name,
            'content' => $content,
        ])->assertCreated()->json('id');
    }

    private function tableForRoute(string $routeName): string
    {
        foreach ($this->categories() as [$storeRoute, , , , $table]) {
            if ($storeRoute === $routeName) {
                return $table;
            }
        }

        self::fail("Table not found for route {$routeName}.");
    }
}
