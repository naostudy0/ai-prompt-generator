<?php

namespace App\Application\PromptPreparation\Writes\DeleteGazePrompt;

use App\Domain\PromptPreparation\Repositories\GazePromptRepository;

final readonly class DeleteGazePromptHandler
{
    public function __construct(private GazePromptRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
