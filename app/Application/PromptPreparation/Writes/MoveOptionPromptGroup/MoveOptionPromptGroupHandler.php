<?php

namespace App\Application\PromptPreparation\Writes\MoveOptionPromptGroup;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;

final readonly class MoveOptionPromptGroupHandler
{
    public function __construct(
        private OptionPromptGroupRepository $repository,
        private TransactionManager $transactions,
    ) {
    }

    public function handle(int $id, ?int $beforeId): void
    {
        $this->transactions->run(fn () => $this->repository->moveBefore($id, $beforeId));
    }
}
