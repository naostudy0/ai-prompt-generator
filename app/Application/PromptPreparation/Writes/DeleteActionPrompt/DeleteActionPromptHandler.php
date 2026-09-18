<?php

namespace App\Application\PromptPreparation\Writes\DeleteActionPrompt;

use App\Domain\PromptPreparation\Repositories\ActionPromptRepository;

final readonly class DeleteActionPromptHandler
{
    public function __construct(private ActionPromptRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
