<?php

namespace App\Application\PromptPreparation\Queries\GetComfyUiWorkflow;

use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;

final readonly class GetComfyUiWorkflowHandler
{
    public function __construct(private ModelFamilyRepository $families, private ComfyUiWorkflowStore $workflows)
    {
    }

    /** @return array{configured: bool, fileName?: string, updatedAt?: string, mappings?: array{positive: array{nodeId: string, inputName: string}, negative: array{nodeId: string, inputName: string}, seed: array{nodeId: string, inputName: string}}} */
    public function handle(int $modelFamilyId): array
    {
        $this->families->ensureExists($modelFamilyId);
        $metadata = $this->workflows->findMetadata($modelFamilyId);

        return $metadata === null ? ['configured' => false] : [
            'configured' => true,
            'fileName' => $metadata['fileName'],
            'updatedAt' => $metadata['updatedAt'],
            'mappings' => $metadata['mappings'],
        ];
    }
}
