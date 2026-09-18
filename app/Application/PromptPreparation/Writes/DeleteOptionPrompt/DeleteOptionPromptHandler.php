<?php

namespace App\Application\PromptPreparation\Writes\DeleteOptionPrompt;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;

final readonly class DeleteOptionPromptHandler
{
    public function __construct(
        private OptionPromptRepository $repository,
        private TransactionManager $transactions,
    ) {
    }

    public function handle(int $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $groupId = $this->repository->get($id)->groupId;
            $this->repository->delete($id);
            $this->repository->normalizePositions($groupId);
        });
    }
}
