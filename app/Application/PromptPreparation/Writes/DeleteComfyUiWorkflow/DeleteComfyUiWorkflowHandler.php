<?php

namespace App\Application\PromptPreparation\Writes\DeleteComfyUiWorkflow;

use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;

final readonly class DeleteComfyUiWorkflowHandler
{
    public function __construct(private ModelFamilyRepository $families, private ComfyUiWorkflowStore $workflows)
    {
    }

    public function handle(int $modelFamilyId): void
    {
        $this->families->ensureExists($modelFamilyId);
        $this->workflows->delete($modelFamilyId);
    }
}
