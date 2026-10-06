<?php

namespace App\Application\PromptPreparation\Writes\SaveOptionCategory;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Models\OptionCategory;
use App\Domain\PromptPreparation\Repositories\OptionCategoryRepository;
use LogicException;

final readonly class SaveOptionCategoryHandler
{
    public function __construct(
        private OptionCategoryRepository $repository,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{id: int, name: string, position: int} */
    public function handle(?int $id, string $name): array
    {
        $category = $this->transactions->run(function () use ($id, $name): OptionCategory {
            $position = $id === null ? $this->repository->nextPosition() : $this->repository->get($id)->position;

            return $this->repository->save(new OptionCategory($id, $name, $position));
        });
        if ($category->id === null) {
            throw new LogicException('The saved option category must have an ID.');
        }

        return ['id' => $category->id, 'name' => $category->name, 'position' => $category->position];
    }
}
