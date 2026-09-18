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

    /** @return array{id: int, name: string, selectionMode: string, position: int, options: array{}} */
    public function handle(?int $id, string $name, OptionSelectionMode $selectionMode): array
    {
        $group = $this->transactions->run(function () use ($id, $name, $selectionMode): OptionPromptGroup {
            $position = $id === null ? $this->repository->nextPosition() : $this->repository->get($id)->position;

            return $this->repository->save(new OptionPromptGroup($id, $name, $selectionMode, $position));
        });
        if ($group->id === null) {
            throw new LogicException('The saved option prompt group must have an ID.');
        }

        return [
            'id' => $group->id,
            'name' => $group->name,
            'selectionMode' => $group->selectionMode->value,
            'position' => $group->position,
            'options' => [],
        ];
    }
}
