<?php

namespace App\Application\PromptPreparation\Services;

use App\Application\PromptPreparation\DTO\MatchedComfyUiWorkflow;
use App\Application\PromptPreparation\Exceptions\UnsupportedComfyUiWorkflow;

final class ComfyUiWorkflowStructureMatcher
{
    private const MASK = '__APP_MUTABLE_INPUT__';

    /**
     * @param array<string, mixed> $imageWorkflow
     * @param list<array{modelFamilyId: int, workflow: array<string, mixed>, mappings: array<string, mixed>}> $registeredWorkflows
     */
    public function match(array $imageWorkflow, array $registeredWorkflows): MatchedComfyUiWorkflow
    {
        $matches = [];
        foreach ($registeredWorkflows as $candidate) {
            $image = $imageWorkflow;
            $registered = $candidate['workflow'];
            $positions = $this->positions($candidate['mappings']);
            foreach ($positions as $position) {
                if (! $this->hasInput($image, $position) || ! $this->hasInput($registered, $position)) {
                    continue 2;
                }
                $image[$position['nodeId']]['inputs'][$position['inputName']] = self::MASK;
                $registered[$position['nodeId']]['inputs'][$position['inputName']] = self::MASK;
            }
            if ($this->canonical($image) === $this->canonical($registered)) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) !== 1) {
            throw new UnsupportedComfyUiWorkflow(
                count($matches) === 0
                    ? '現在登録されているワークフローに一致しません。'
                    : 'ワークフローを一意に特定できません。',
            );
        }

        $matched = $matches[0];
        $positive = $this->promptValue($imageWorkflow, $matched['mappings']['positive'] ?? null);
        $negative = $this->promptValue($imageWorkflow, $matched['mappings']['negative'] ?? null);

        return new MatchedComfyUiWorkflow($matched['modelFamilyId'], $positive, $negative);
    }

    /** @param array<string, mixed> $mappings
     *  @return list<array{nodeId: string, inputName: string}>
     */
    private function positions(array $mappings): array
    {
        $positions = [];
        foreach (['positive', 'negative', 'seed'] as $role) {
            $mapping = $mappings[$role] ?? null;
            if (is_array($mapping) && is_string($mapping['nodeId'] ?? null) && is_string($mapping['inputName'] ?? null)) {
                $positions[] = ['nodeId' => $mapping['nodeId'], 'inputName' => $mapping['inputName']];
            }
        }
        foreach (['checkpoint', 'sampler', 'scheduler', 'outputFilenamePrefix'] as $role) {
            foreach (is_array($mappings[$role] ?? null) ? $mappings[$role] : [] as $mapping) {
                if (is_array($mapping) && is_string($mapping['nodeId'] ?? null) && is_string($mapping['inputName'] ?? null)) {
                    $positions[] = ['nodeId' => $mapping['nodeId'], 'inputName' => $mapping['inputName']];
                }
            }
        }

        return $positions;
    }

    /** @param array<string, mixed> $workflow
     *  @param array{nodeId: string, inputName: string} $position
     */
    private function hasInput(array $workflow, array $position): bool
    {
        return isset($workflow[$position['nodeId']]['inputs'])
            && is_array($workflow[$position['nodeId']]['inputs'])
            && array_key_exists($position['inputName'], $workflow[$position['nodeId']]['inputs']);
    }

    /** @param array<string, mixed> $workflow */
    private function promptValue(array $workflow, mixed $position): string
    {
        if (! is_array($position)
            || ! is_string($position['nodeId'] ?? null)
            || ! is_string($position['inputName'] ?? null)
            || ! $this->hasInput($workflow, $position)) {
            throw new UnsupportedComfyUiWorkflow('プロンプトの入力位置が不正です。');
        }
        $value = $workflow[$position['nodeId']]['inputs'][$position['inputName']];
        if (! is_string($value)) {
            throw new UnsupportedComfyUiWorkflow('画像内のプロンプトが文字列ではありません。');
        }

        return $value;
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
        }
        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
    }
}
