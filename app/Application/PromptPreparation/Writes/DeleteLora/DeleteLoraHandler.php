<?php

namespace App\Application\PromptPreparation\Writes\DeleteLora;

use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Repositories\LoraRepository;

final readonly class DeleteLoraHandler
{
    public function __construct(private LoraRepository $repository)
    {
    }

    public function handle(int $id, LoraKind $kind): void
    {
        $this->repository->delete($id, $kind);
    }
}
