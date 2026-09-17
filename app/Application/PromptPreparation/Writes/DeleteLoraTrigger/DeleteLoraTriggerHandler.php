<?php

namespace App\Application\PromptPreparation\Writes\DeleteLoraTrigger;

use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;

final readonly class DeleteLoraTriggerHandler
{
    public function __construct(private LoraTriggerRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
