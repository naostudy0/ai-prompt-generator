<?php

namespace App\Application\PromptPreparation\Writes\DeleteLoraTrigger;

use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;

final readonly class DeleteLoraTriggerHandler
{
    public function __construct(private LoraTriggerRepository $repository)
    {
    }

    public function handle(int $id, LoraKind $kind): void
    {
        $this->repository->delete($id, $kind);
    }
}
