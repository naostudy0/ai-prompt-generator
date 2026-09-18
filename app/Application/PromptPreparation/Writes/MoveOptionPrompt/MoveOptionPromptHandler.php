<?php

namespace App\Application\PromptPreparation\Writes\MoveOptionPrompt;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;

final readonly class MoveOptionPromptHandler
{
    public function __construct(
        private OptionPromptRepository $repository,
        private TransactionManager $transactions,
    ) {
    }

    public function handle(int $id, int $targetGroupId, ?int $beforeId): void
    {
        $this->transactions->run(fn () => $this->repository->moveBefore($id, $targetGroupId, $beforeId));
    }
}
