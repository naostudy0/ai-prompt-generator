<?php

namespace App\Application\PromptPreparation\Writes\DeleteOutfitPrompt;

use App\Domain\PromptPreparation\Repositories\OutfitPromptRepository;

final readonly class DeleteOutfitPromptHandler
{
    public function __construct(private OutfitPromptRepository $repository)
    {
    }

    public function handle(int $id): void
    {
        $this->repository->delete($id);
    }
}
