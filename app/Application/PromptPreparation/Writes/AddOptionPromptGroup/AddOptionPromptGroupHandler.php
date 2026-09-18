<?php

namespace App\Application\PromptPreparation\Writes\AddOptionPromptGroup;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;

final readonly class AddOptionPromptGroupHandler
{
    public function __construct(
        private OptionPromptGroupRepository $repository,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{id: int, position: int, label: string, options: array{}} */
    public function handle(): array
    {
        $group = $this->transactions->run(fn (): OptionPromptGroup => $this->repository->save(
            new OptionPromptGroup(id: null, position: $this->repository->nextPosition()),
        ));

        return ['id' => (int) $group->id, 'position' => $group->position,
            'label' => $group->label(), 'options' => []];
    }
}
