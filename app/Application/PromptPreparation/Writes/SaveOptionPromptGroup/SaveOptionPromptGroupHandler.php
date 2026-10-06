<?php

namespace App\Application\PromptPreparation\Writes\SaveOptionPromptGroup;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use App\Domain\PromptPreparation\Models\OptionSelectionMode;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;
use LogicException;

final readonly class SaveOptionPromptGroupHandler
{
    public function __construct(
        private OptionPromptGroupRepository $repository,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{id: int, categoryId: ?int, name: string, selectionMode: string, position: int, options: array{}} */
    public function handle(?int $id, string $name, OptionSelectionMode $selectionMode): array
    {
        $group = $this->transactions->run(function () use ($id, $name, $selectionMode): OptionPromptGroup {
            $current = $id === null ? null : $this->repository->getForUpdate($id);
            $position = $current === null ? $this->repository->nextPosition() : $current->position;

            return $this->repository->save(new OptionPromptGroup(
                $id,
                $name,
                $selectionMode,
                $position,
                $current?->categoryId,
            ));
        });
        if ($group->id === null) {
            throw new LogicException('The saved option prompt group must have an ID.');
        }

        return [
            'id' => $group->id,
            'categoryId' => $group->categoryId,
            'name' => $group->name,
            'selectionMode' => $group->selectionMode->value,
            'position' => $group->position,
            'options' => [],
        ];
    }
}
