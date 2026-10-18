<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups\IllustrationPromptTagGroupInput;
use App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups\SaveIllustrationPromptTagGroupsHandler;
use App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups\SaveIllustrationPromptTagGroupsInput;
use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use App\Domain\PromptPreparation\Models\OptionSelectionMode;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use Closure;
use PHPUnit\Framework\TestCase;

final class SaveIllustrationPromptTagGroupsHandlerTest extends TestCase
{
    public function test_初回グループを作成して重複を除いた候補を提案順で保存する(): void
    {
        $groups = new InMemoryOptionPromptGroupRepository();
        $prompts = new InMemoryOptionPromptRepository();
        $handler = new SaveIllustrationPromptTagGroupsHandler(
            $groups,
            $prompts,
            new ImmediateTransactionManager(),
        );

        $result = $handler->handle(new SaveIllustrationPromptTagGroupsInput([
            new IllustrationPromptTagGroupInput('最初', ' Tag One , tag two '),
            new IllustrationPromptTagGroupInput('重複', 'TAG TWO, tag one'),
            new IllustrationPromptTagGroupInput('次', 'sunset, classroom'),
        ]));

        self::assertSame(1, $result->groupId);
        self::assertSame(2, $result->savedCount);
        self::assertSame(1, $result->duplicateCount);
        self::assertSame('ai_prompt_suggestions', $groups->group?->systemKey);
        self::assertSame('AIプロンプト', $groups->group?->name);
        self::assertSame(OptionSelectionMode::Multiple, $groups->group?->selectionMode);
        self::assertNull($groups->group?->categoryId);
        self::assertSame(['Tag One, tag two,', 'sunset, classroom,'], array_map(
            static fn (OptionPrompt $prompt): string => $prompt->content->value,
            $prompts->prompts,
        ));
        self::assertSame([1, 2], array_map(
            static fn (OptionPrompt $prompt): int => $prompt->position,
            $prompts->prompts,
        ));
    }

    public function test_既存グループ内だけを重複判定して末尾へ保存する(): void
    {
        $groups = new InMemoryOptionPromptGroupRepository(new OptionPromptGroup(
            8,
            'AIプロンプト',
            OptionSelectionMode::Multiple,
            3,
            null,
            'ai_prompt_suggestions',
        ));
        $prompts = new InMemoryOptionPromptRepository([
            new OptionPrompt(10, 8, '既存', PromptText::fromInput('classroom, sunset'), 1),
            new OptionPrompt(11, 99, '移動済み', PromptText::fromInput('long hair, black hair'), 1),
        ]);
        $handler = new SaveIllustrationPromptTagGroupsHandler(
            $groups,
            $prompts,
            new ImmediateTransactionManager(),
        );

        $result = $handler->handle(new SaveIllustrationPromptTagGroupsInput([
            new IllustrationPromptTagGroupInput('既存と同じ', ' Sunset , CLASSROOM '),
            new IllustrationPromptTagGroupInput('移動済みと同じ', 'black hair, long hair'),
        ]));

        self::assertSame(8, $result->groupId);
        self::assertSame(1, $result->savedCount);
        self::assertSame(1, $result->duplicateCount);
        self::assertSame('black hair, long hair,', $prompts->prompts[2]->content->value);
        self::assertSame(2, $prompts->prompts[2]->position);
    }
}

final class ImmediateTransactionManager implements TransactionManager
{
    public function run(Closure $operation): mixed
    {
        return $operation();
    }
}

final class InMemoryOptionPromptGroupRepository implements OptionPromptGroupRepository
{
    public function __construct(public ?OptionPromptGroup $group = null)
    {
    }

    public function get(int $id): OptionPromptGroup
    {
        return $this->group ?? throw new \RuntimeException();
    }

    public function getForUpdate(int $id): OptionPromptGroup
    {
        return $this->get($id);
    }

    public function findBySystemKeyForUpdate(string $systemKey): ?OptionPromptGroup
    {
        return $this->group?->systemKey === $systemKey ? $this->group : null;
    }

    public function nextPosition(): int
    {
        return ($this->group?->position ?? 0) + 1;
    }

    public function save(OptionPromptGroup $group): OptionPromptGroup
    {
        $this->group = new OptionPromptGroup(
            $group->id ?? 1,
            $group->name,
            $group->selectionMode,
            $group->position,
            $group->categoryId,
            $group->systemKey,
        );

        return $this->group;
    }

    public function moveBefore(int $id, ?int $beforeId): void
    {
    }

    public function clearCategoryAssignments(int $categoryId): void
    {
    }
}

final class InMemoryOptionPromptRepository implements OptionPromptRepository
{
    /** @param list<OptionPrompt> $prompts */
    public function __construct(public array $prompts = [])
    {
    }

    public function get(int $id): OptionPrompt
    {
        return $this->prompts[$id] ?? throw new \RuntimeException();
    }

    /** @return list<OptionPrompt> */
    public function getAllInGroupForUpdate(int $groupId): array
    {
        return array_values(array_filter(
            $this->prompts,
            static fn (OptionPrompt $prompt): bool => $prompt->groupId === $groupId,
        ));
    }

    public function nextPosition(int $groupId): int
    {
        return count($this->getAllInGroupForUpdate($groupId)) + 1;
    }

    public function save(OptionPrompt $prompt): OptionPrompt
    {
        $saved = new OptionPrompt(
            $prompt->id ?? count($this->prompts) + 1,
            $prompt->groupId,
            $prompt->name,
            $prompt->content,
            $prompt->position,
        );
        $this->prompts[] = $saved;

        return $saved;
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
}
