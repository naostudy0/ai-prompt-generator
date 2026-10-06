<?php

namespace App\Application\PromptPreparation\Writes\ChangeOptionPromptGroupCategory;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Repositories\OptionCategoryRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;

final readonly class ChangeOptionPromptGroupCategoryHandler
{
    public function __construct(
        private OptionPromptGroupRepository $repository,
        private OptionCategoryRepository $categories,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{id: int, categoryId: ?int} */
    public function handle(int $id, ?int $categoryId): array
    {
        $group = $this->transactions->run(function () use ($id, $categoryId) {
            if ($categoryId !== null) {
                $this->categories->getForUpdate($categoryId);
            }

            $group = $this->repository->getForUpdate($id);

            return $this->repository->save($group->changeCategory($categoryId));
        });

        return ['id' => (int) $group->id, 'categoryId' => $group->categoryId];
    }
}
