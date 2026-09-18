<?php

namespace App\Application\PromptPreparation\Writes\DeleteOptionPrompt;

use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;

final readonly class DeleteOptionPromptHandler
{
    public function __construct(private OptionPromptRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
