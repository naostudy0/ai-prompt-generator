<?php

namespace App\Application\PromptPreparation\Writes\DeleteExpressionPrompt;

use App\Domain\PromptPreparation\Repositories\ExpressionPromptRepository;

final readonly class DeleteExpressionPromptHandler
{
    public function __construct(private ExpressionPromptRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
