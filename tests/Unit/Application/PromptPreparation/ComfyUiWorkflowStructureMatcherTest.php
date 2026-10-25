<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\Exceptions\UnsupportedComfyUiWorkflow;
use App\Application\PromptPreparation\Services\ComfyUiWorkflowStructureMatcher;
use PHPUnit\Framework\TestCase;

final class ComfyUiWorkflowStructureMatcherTest extends TestCase
{
    public function test_可変入力だけが異なる登録ワークフローから両プロンプトを取得する(): void
    {
        $image = $this->workflow('imported positive,', 'imported negative,', 999, 'model-b');
        $registered = $this->workflow('saved positive,', 'saved negative,', 1, 'model-a');

        $result = (new ComfyUiWorkflowStructureMatcher())->match($image, [$this->candidate(7, $registered)]);

        self::assertSame(7, $result->modelFamilyId);
        self::assertSame('imported positive,', $result->positivePrompt);
        self::assertSame('imported negative,', $result->negativePrompt);
    }

    public function test_JSONオブジェクトのキー順が異なっても一致する(): void
    {
        $image = $this->workflow('positive,', 'negative,', 2, 'model-a');
        $image = array_reverse($image, true);

        $result = (new ComfyUiWorkflowStructureMatcher())->match($image, [$this->candidate(3, $this->workflow())]);

        self::assertSame(3, $result->modelFamilyId);
    }

    public function test_固定入力または接続が異なるワークフローを拒否する(): void
    {
        $image = $this->workflow();
        $image['node-3']['inputs']['fixed'] = 'changed';
        $this->expectException(UnsupportedComfyUiWorkflow::class);

        (new ComfyUiWorkflowStructureMatcher())->match($image, [$this->candidate(1, $this->workflow())]);
    }

    public function test_複数の登録ワークフローに一致する場合は拒否する(): void
    {
        $this->expectException(UnsupportedComfyUiWorkflow::class);
        $candidate = $this->candidate(1, $this->workflow());

        (new ComfyUiWorkflowStructureMatcher())->match($this->workflow(), [$candidate, [...$candidate, 'modelFamilyId' => 2]]);
    }

    public function test_プロンプト入力が文字列でない場合は拒否する(): void
    {
        $image = $this->workflow();
        $image['node-1']['inputs']['text'] = ['invalid'];
        $this->expectException(UnsupportedComfyUiWorkflow::class);

        (new ComfyUiWorkflowStructureMatcher())->match($image, [$this->candidate(1, $this->workflow())]);
    }

    /** @return array<string, mixed> */
    private function workflow(string $positive = 'positive,', string $negative = 'negative,', int $seed = 1, string $checkpoint = 'model-a'): array
    {
        return [
            'node-1' => ['inputs' => ['text' => $positive], 'class_type' => 'Text'],
            'node-2' => ['inputs' => ['text' => $negative], 'class_type' => 'Text'],
            'node-3' => ['inputs' => ['seed' => $seed, 'model' => $checkpoint, 'fixed' => 'same', 'link' => ['node-1', 0]], 'class_type' => 'Sampler'],
        ];
    }

    /** @param array<string, mixed> $workflow
     *  @return array{modelFamilyId: int, workflow: array<string, mixed>, mappings: array<string, mixed>}
     */
    private function candidate(int $id, array $workflow): array
    {
        return ['modelFamilyId' => $id, 'workflow' => $workflow, 'mappings' => [
            'positive' => ['nodeId' => 'node-1', 'inputName' => 'text'],
            'negative' => ['nodeId' => 'node-2', 'inputName' => 'text'],
            'seed' => ['nodeId' => 'node-3', 'inputName' => 'seed'],
            'checkpoint' => [['nodeId' => 'node-3', 'inputName' => 'model']],
            'sampler' => [], 'scheduler' => [], 'outputFilenamePrefix' => [],
        ]];
    }
}
