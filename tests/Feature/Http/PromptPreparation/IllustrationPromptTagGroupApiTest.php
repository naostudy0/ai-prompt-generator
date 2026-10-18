<?php

namespace Tests\Feature\Http\PromptPreparation;

use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class IllustrationPromptTagGroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_選択候補をシステム管理ブロックへ保存して重複件数を返す(): void
    {
        DB::table('option_prompt_groups')->insert([
            'name' => 'AIプロンプト',
            'selection_mode' => 'multiple',
            'position' => 7,
            'option_category_id' => null,
            'system_key' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = $this->postJson(route('illustration-prompt-tag-groups.store'), [
            'groups' => [
                ['label' => '夕暮れの教室', 'tags' => 'classroom, sunset'],
                ['label' => '同じ集合', 'tags' => ' Sunset , CLASSROOM '],
            ],
        ])->assertOk()->assertJson([
            'savedCount' => 1,
            'duplicateCount' => 1,
        ]);

        $groupId = (int) $first->json('groupId');
        $this->assertDatabaseHas('option_prompt_groups', [
            'id' => $groupId,
            'name' => 'AIプロンプト',
            'selection_mode' => 'multiple',
            'option_category_id' => null,
            'system_key' => 'ai_prompt_suggestions',
        ]);
        $this->assertDatabaseHas('option_prompt_groups', [
            'name' => 'AIプロンプト',
            'system_key' => null,
        ]);
        $this->assertDatabaseHas('option_prompts', [
            'option_prompt_group_id' => $groupId,
            'name' => '夕暮れの教室',
            'content' => 'classroom, sunset,',
            'position' => 1,
        ]);

        $this->postJson(route('illustration-prompt-tag-groups.store'), [
            'groups' => [['label' => '既存', 'tags' => 'sunset,classroom']],
        ])->assertOk()->assertExactJson([
            'groupId' => $groupId,
            'savedCount' => 0,
            'duplicateCount' => 1,
        ]);
    }

    public function test_境界件数とラベル長を受け付ける(): void
    {
        $groups = array_fill(0, 10, ['label' => str_repeat('あ', 100), 'tags' => 'tag']);
        foreach ($groups as $index => &$group) {
            $group['tags'] = 'tag_'.$index;
        }

        $this->postJson(route('illustration-prompt-tag-groups.store'), ['groups' => $groups])
            ->assertOk()
            ->assertJson(['savedCount' => 10, 'duplicateCount' => 0]);
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_不正な候補を422で拒否する(array $payload): void
    {
        $this->postJson(route('illustration-prompt-tag-groups.store'), $payload)
            ->assertUnprocessable();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): array
    {
        return [
            'missing' => [[]],
            'not array' => [['groups' => 'group']],
            'empty' => [['groups' => []]],
            'too many' => [['groups' => array_fill(0, 11, ['label' => '場面', 'tags' => 'sunset'])]],
            'empty label' => [['groups' => [['label' => '　', 'tags' => 'sunset']]]],
            'long label' => [['groups' => [['label' => str_repeat('あ', 101), 'tags' => 'sunset']]]],
            'empty tags' => [['groups' => [['label' => '場面', 'tags' => ' ,　, ']]]],
            'wrong tags type' => [['groups' => [['label' => '場面', 'tags' => 1]]]],
        ];
    }

    public function test_項目保存失敗時は作成したブロックもロールバックする(): void
    {
        $this->app->instance(OptionPromptRepository::class, new class () implements OptionPromptRepository {
            public function get(int $id): OptionPrompt
            {
                throw new RuntimeException('not used');
            }

            public function getAllInGroupForUpdate(int $groupId): array
            {
                return [];
            }

            public function nextPosition(int $groupId): int
            {
                return 1;
            }

            public function save(OptionPrompt $prompt): OptionPrompt
            {
                throw new RuntimeException('SAVE SECRET');
            }

            public function delete(int $id): void
            {
            }

            public function normalizePositions(int $groupId): void
            {
            }

            public function moveBefore(int $id, int $targetGroupId, ?int $beforeId): void
            {
            }
        });
        config()->set('app.debug', false);

        $this->postJson(route('illustration-prompt-tag-groups.store'), [
            'groups' => [['label' => '場面', 'tags' => 'sunset']],
        ])->assertInternalServerError();

        $this->assertDatabaseMissing('option_prompt_groups', [
            'system_key' => 'ai_prompt_suggestions',
        ]);
    }
}
