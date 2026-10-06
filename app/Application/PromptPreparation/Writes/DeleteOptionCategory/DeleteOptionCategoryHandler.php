<?php

namespace App\Application\PromptPreparation\Writes\DeleteOptionCategory;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Repositories\OptionCategoryRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;

final readonly class DeleteOptionCategoryHandler
{
    public function __construct(
        private OptionCategoryRepository $repository,
        private OptionPromptGroupRepository $groups,
        private TransactionManager $transactions,
    ) {
    }

    public function handle(int $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $this->repository->getForUpdate($id);
            $this->groups->clearCategoryAssignments($id);
            $this->repository->delete($id);
        });
    }
}
