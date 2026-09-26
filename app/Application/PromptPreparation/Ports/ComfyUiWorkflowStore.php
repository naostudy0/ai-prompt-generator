<?php

namespace App\Application\PromptPreparation\Ports;

interface ComfyUiWorkflowStore
{
    /** @param array{positive: array{nodeId: string, inputName: string}, negative: array{nodeId: string, inputName: string}, seed: array{nodeId: string, inputName: string}} $mappings */
    public function save(int $modelFamilyId, string $fileName, string $json, array $mappings): void;

    /** @return array{modelFamilyId: int, fileName: string, updatedAt: string, mappings: array{positive: array{nodeId: string, inputName: string}, negative: array{nodeId: string, inputName: string}, seed: array{nodeId: string, inputName: string}}}|null */
    public function findMetadata(int $modelFamilyId): ?array;

    /** @return array{workflow: array<string, mixed>, mappings: array{positive: array{nodeId: string, inputName: string}, negative: array{nodeId: string, inputName: string}, seed: array{nodeId: string, inputName: string}}}|null */
    public function findWorkflow(int $modelFamilyId): ?array;

    public function delete(int $modelFamilyId): void;
}
