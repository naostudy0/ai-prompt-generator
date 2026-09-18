<?php

namespace App\Application\PromptPreparation\Writes\DeleteLocationPrompt;

use App\Domain\PromptPreparation\Repositories\LocationPromptRepository;

final readonly class DeleteLocationPromptHandler
{
    public function __construct(private LocationPromptRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
