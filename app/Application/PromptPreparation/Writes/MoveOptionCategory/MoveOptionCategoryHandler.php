<?php

namespace App\Application\PromptPreparation\Writes\MoveOptionCategory;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Repositories\OptionCategoryRepository;

final readonly class MoveOptionCategoryHandler
{
    public function __construct(private OptionCategoryRepository $repository, private TransactionManager $transactions)
    {
    }

    public function handle(int $id, ?int $beforeId): void
    {
        $this->transactions->run(fn () => $this->repository->moveBefore($id, $beforeId));
    }
}
