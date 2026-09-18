<?php

namespace App\Application\PromptPreparation\Writes\DeleteCompositionPrompt;

use App\Domain\PromptPreparation\Repositories\CompositionPromptRepository;

final readonly class DeleteCompositionPromptHandler
{
    public function __construct(private CompositionPromptRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
