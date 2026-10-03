<?php

namespace App\Application\PromptPreparation\Writes\SaveComfyUiWorkflow;

use App\Application\PromptPreparation\Exceptions\InvalidComfyUiWorkflow;
use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;
use JsonException;

final readonly class SaveComfyUiWorkflowHandler
{
    public function __construct(
        private ModelFamilyRepository $families,
        private ComfyUiWorkflowStore $workflows,
    ) {
    }

    /** @param array{positive: array{nodeId: string, inputName: string}, negative: array{nodeId: string, inputName: string}, seed: array{nodeId: string, inputName: string}, checkpoint: list<array{nodeId: string, inputName: string}>, sampler: list<array{nodeId: string, inputName: string}>, scheduler: list<array{nodeId: string, inputName: string}>, outputFilenamePrefix: list<array{nodeId: string, inputName: string}>} $mappings */
    public function handle(int $modelFamilyId, string $fileName, string $json, array $mappings): void
    {
        $this->families->ensureExists($modelFamilyId);
        try {
            $workflow = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidComfyUiWorkflow('JSONを読み取れません。', previous: $exception);
        }
        if (!is_array($workflow) || array_is_list($workflow)) {
            throw new InvalidComfyUiWorkflow('ワークフローのルートは空でないオブジェクトにしてください。');
        }
        foreach (['positive', 'negative', 'seed'] as $role) {
            $mapping = $mappings[$role];
            $value = $workflow[$mapping['nodeId']]['inputs'][$mapping['inputName']] ?? null;
            $valid = $role === 'seed' ? is_int($value) : is_string($value);
            if (!$valid) {
                throw new InvalidComfyUiWorkflow("{$role}のノードIDまたは入力名が不正です。");
            }
        }
        foreach (['checkpoint', 'sampler', 'scheduler', 'outputFilenamePrefix'] as $role) {
            $locations = [];
            foreach ($mappings[$role] as $mapping) {
                $location = $mapping['nodeId']."\0".$mapping['inputName'];
                if (isset($locations[$location])) {
                    throw new InvalidComfyUiWorkflow("{$role}に同じ入力位置が重複しています。");
                }
                $locations[$location] = true;
                $value = $workflow[$mapping['nodeId']]['inputs'][$mapping['inputName']] ?? null;
                if (!is_string($value)) {
                    throw new InvalidComfyUiWorkflow("{$role}のノードIDまたは入力名が不正です。");
                }
            }
        }
        $this->workflows->save($modelFamilyId, $fileName, $json, $mappings);
    }
}
