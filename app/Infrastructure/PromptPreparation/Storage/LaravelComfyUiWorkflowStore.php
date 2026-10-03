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
                DB::table('comfy_ui_workflow_mappings')->where('model_family_id', $modelFamilyId)->delete();
                $rows = [];
                $allMappings = [
                    'positive' => [$mappings['positive']],
                    'negative' => [$mappings['negative']],
                    'seed' => [$mappings['seed']],
                    'checkpoint' => $mappings['checkpoint'],
                    'sampler' => $mappings['sampler'],
                    'scheduler' => $mappings['scheduler'],
                    'outputFilenamePrefix' => $mappings['outputFilenamePrefix'],
                ];
                foreach ($allMappings as $role => $normalized) {
                    foreach ($normalized as $position => $mapping) {
                        $rows[] = [
                            'model_family_id' => $modelFamilyId,
                            'role' => $role,
                            'node_id' => $mapping['nodeId'],
                            'input_name' => $mapping['inputName'],
                            'position' => $position,
                        ];
                    }
                }
                DB::table('comfy_ui_workflow_mappings')->insert($rows);
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

    /** @return array{positive: array{nodeId: string, inputName: string}, negative: array{nodeId: string, inputName: string}, seed: array{nodeId: string, inputName: string}, checkpoint: list<array{nodeId: string, inputName: string}>, sampler: list<array{nodeId: string, inputName: string}>, scheduler: list<array{nodeId: string, inputName: string}>, outputFilenamePrefix: list<array{nodeId: string, inputName: string}>} */
    private function mappings(ComfyUiWorkflowRecord $record): array
    {
        $grouped = DB::table('comfy_ui_workflow_mappings')
            ->where('model_family_id', $record->getKey())->orderBy('position')->get()->groupBy('role');
        /** @var array<string, list<array{nodeId: string, inputName: string}>> $lists */
        $lists = [];
        foreach (['positive', 'negative', 'seed', 'checkpoint', 'sampler', 'scheduler', 'outputFilenamePrefix'] as $role) {
            $values = ($grouped->get($role) ?? collect())->map(fn (object $row): array => [
                'nodeId' => (string) data_get($row, 'node_id'),
                'inputName' => (string) data_get($row, 'input_name'),
            ])->values()->all();
            $lists[$role] = array_values($values);
        }

        return [
            'positive' => $lists['positive'][0] ?? ['nodeId' => '', 'inputName' => ''],
            'negative' => $lists['negative'][0] ?? ['nodeId' => '', 'inputName' => ''],
            'seed' => $lists['seed'][0] ?? ['nodeId' => '', 'inputName' => ''],
            'checkpoint' => $lists['checkpoint'],
            'sampler' => $lists['sampler'],
            'scheduler' => $lists['scheduler'],
            'outputFilenamePrefix' => $lists['outputFilenamePrefix'],
        ];
    }
}
