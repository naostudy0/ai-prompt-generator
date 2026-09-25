<?php

namespace App\Application\PromptPreparation\Writes\DeleteModelFamily;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;

final readonly class DeleteModelFamilyHandler
{
    public function __construct(
        private ModelFamilyRepository $families,
        private TransactionManager $transactions,
    ) {
    }

    public function handle(int $id): void
    {
        $this->transactions->run(fn () => $this->families->delete($id));
    }
}
