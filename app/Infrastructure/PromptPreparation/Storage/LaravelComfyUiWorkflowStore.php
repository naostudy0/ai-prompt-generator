<?php

namespace App\Infrastructure\PromptPreparation\Storage;

use App\Application\PromptPreparation\Exceptions\InvalidComfyUiWorkflow;
use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\ComfyUiWorkflowRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class LaravelComfyUiWorkflowStore implements ComfyUiWorkflowStore
{
    public function save(int $modelFamilyId, string $fileName, string $json, array $mappings): void
    {
        $newPath = sprintf('comfyui-workflows/%d/%s.json', $modelFamilyId, Str::uuid());
        if (!Storage::disk('local')->put($newPath, $json)) {
            throw new RuntimeException('ワークフローを保存できませんでした。');
        }
        $oldPath = ComfyUiWorkflowRecord::query()->find($modelFamilyId)?->getAttribute('storage_path');
        try {
            DB::transaction(function () use ($modelFamilyId, $fileName, $newPath, $mappings): void {
                ComfyUiWorkflowRecord::query()->updateOrCreate(
                    ['model_family_id' => $modelFamilyId],
                    [
                        'original_file_name' => $fileName,
                        'storage_path' => $newPath,
                        'positive_node_id' => $mappings['positive']['nodeId'],
                        'positive_input_name' => $mappings['positive']['inputName'],
                        'negative_node_id' => $mappings['negative']['nodeId'],
                        'negative_input_name' => $mappings['negative']['inputName'],
                        'seed_node_id' => $mappings['seed']['nodeId'],
                        'seed_input_name' => $mappings['seed']['inputName'],
                    ],
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }
        if (is_string($oldPath) && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }
    }

    public function findMetadata(int $modelFamilyId): ?array
    {
        $record = ComfyUiWorkflowRecord::query()->find($modelFamilyId);
        if (!$record instanceof ComfyUiWorkflowRecord) {
            return null;
        }

        return [
            'modelFamilyId' => $modelFamilyId,
            'fileName' => (string) $record->getAttribute('original_file_name'),
            'updatedAt' => (string) $record->getAttribute('updated_at'),
            'mappings' => $this->mappings($record),
        ];
    }

    public function findWorkflow(int $modelFamilyId): ?array
    {
        $record = ComfyUiWorkflowRecord::query()->find($modelFamilyId);
        if (!$record instanceof ComfyUiWorkflowRecord) {
            return null;
        }
        $json = Storage::disk('local')->get((string) $record->getAttribute('storage_path'));
        if (!is_string($json)) {
            throw new InvalidComfyUiWorkflow('保存済みワークフローを読み取れません。');
        }
        try {
            $workflow = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidComfyUiWorkflow('保存済みワークフローを読み取れません。', previous: $exception);
        }
        if (!is_array($workflow) || array_is_list($workflow)) {
            throw new InvalidComfyUiWorkflow('保存済みワークフローが不正です。');
        }

        return ['workflow' => $workflow, 'mappings' => $this->mappings($record)];
    }

    public function delete(int $modelFamilyId): void
    {
        $record = ComfyUiWorkflowRecord::query()->find($modelFamilyId);
        if (!$record instanceof ComfyUiWorkflowRecord) {
            return;
        }
        $path = (string) $record->getAttribute('storage_path');
        $record->delete();
        Storage::disk('local')->delete($path);
    }

    /** @return array{positive: array{nodeId: string, inputName: string}, negative: array{nodeId: string, inputName: string}, seed: array{nodeId: string, inputName: string}} */
    private function mappings(ComfyUiWorkflowRecord $record): array
    {
        return [
            'positive' => ['nodeId' => (string) $record->getAttribute('positive_node_id'), 'inputName' => (string) $record->getAttribute('positive_input_name')],
            'negative' => ['nodeId' => (string) $record->getAttribute('negative_node_id'), 'inputName' => (string) $record->getAttribute('negative_input_name')],
            'seed' => ['nodeId' => (string) $record->getAttribute('seed_node_id'), 'inputName' => (string) $record->getAttribute('seed_input_name')],
        ];
    }
}
