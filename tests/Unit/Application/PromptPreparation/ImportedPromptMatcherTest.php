<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\Queries\GetClothingLoraOptions\GetClothingLoraOptionsResult;
use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsResult;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsResult;
use App\Application\PromptPreparation\Queries\GetPromptOptions\GetPromptOptionsResult;
use App\Application\PromptPreparation\Services\ImportedPromptMatcher;
use PHPUnit\Framework\TestCase;

final class ImportedPromptMatcherTest extends TestCase
{
    public function test_全文一致するデフォルトと複数タグ項目だけを選択する(): void
    {
        $result = $this->matcher()->match(
            "quality,\n\nblue eyes, long hair, extra,",
            'bad anatomy,',
            1,
            new GetDefaultPromptsResult('quality,', 'bad anatomy,'),
            $this->characters(),
            $this->clothing(),
            $this->options(),
        );

        self::assertSame(['positive' => true, 'negative' => true], $result->selectionSnapshot->defaults);
        self::assertSame([10], $result->selectionSnapshot->optionIds);
        self::assertSame(['extra'], array_column($result->review['unregistered'], 'content'));
    }

    public function test_一部だけ一致する項目とsingle内の競合を自動選択しない(): void
    {
        $options = new GetPromptOptionsResult([[
            'id' => 1, 'categoryId' => null, 'name' => '表情', 'selectionMode' => 'single', 'position' => 1,
            'systemManaged' => false,
            'options' => [
                ['id' => 10, 'position' => 1, 'name' => 'A', 'content' => 'blue eyes,'],
                ['id' => 11, 'position' => 2, 'name' => 'B', 'content' => 'blue eyes,'],
                ['id' => 12, 'position' => 3, 'name' => 'C', 'content' => 'blue eyes, long hair,'],
            ],
        ]]);

        $result = $this->matcher()->match('blue eyes,', '', 1, new GetDefaultPromptsResult('', ''), $this->characters(), $this->clothing(), $options);

        self::assertSame([], $result->selectionSnapshot->optionIds);
        self::assertNotEmpty($result->review['partial']);
        self::assertNotEmpty($result->review['conflicts']);
    }

    public function test_丸括弧内のカンマを未登録候補として分割しない(): void
    {
        $result = $this->matcher()->match('(smile, open mouth:1.2), blue sky,', '', 1, new GetDefaultPromptsResult('', ''), $this->characters(), $this->clothing(), new GetPromptOptionsResult([]));

        self::assertSame(['(smile, open mouth:1.2)', 'blue sky'], array_column($result->review['unregistered'], 'content'));
    }

    public function test_登録済みLoRAは対応強度だけを復元し非表示と範囲外を確認対象にする(): void
    {
        $result = $this->matcher()->match(
            '<lora:hero.safetensors:0.8>, <lora:hidden.safetensors:1>, <lora:dress.safetensors:0.85>,',
            '', 1, new GetDefaultPromptsResult('', ''), $this->characters(), $this->clothing(), new GetPromptOptionsResult([]),
        );

        self::assertSame(1, $result->selectionSnapshot->lora['loraId']);
        self::assertSame(0.8, $result->selectionSnapshot->lora['strength']);
        self::assertSame([], $result->selectionSnapshot->clothingLoras);
        self::assertSame(['hidden_lora', 'unsupported_lora_strength'], array_column($result->review['loras'], 'kind'));
    }

    private function matcher(): ImportedPromptMatcher
    {
        return new ImportedPromptMatcher;
    }

    private function characters(): GetLoraPromptOptionsResult
    {
        return new GetLoraPromptOptionsResult([
            ['id' => 1, 'name' => 'Hero', 'fileName' => 'hero.safetensors', 'recommendedStrength' => 1, 'modelFamilyId' => 1, 'checkpointOptionId' => 1, 'samplerOptionId' => 1, 'schedulerOptionId' => 1, 'isHidden' => false, 'tags' => []],
            ['id' => 2, 'name' => 'Hidden', 'fileName' => 'hidden.safetensors', 'recommendedStrength' => 1, 'modelFamilyId' => 1, 'checkpointOptionId' => 1, 'samplerOptionId' => 1, 'schedulerOptionId' => 1, 'isHidden' => true, 'tags' => []],
        ], [], []);
    }

    private function clothing(): GetClothingLoraOptionsResult
    {
        return new GetClothingLoraOptionsResult([
            ['id' => 3, 'name' => 'Dress', 'fileName' => 'dress.safetensors', 'recommendedStrength' => 1, 'tags' => []],
        ], []);
    }

    private function options(): GetPromptOptionsResult
    {
        return new GetPromptOptionsResult([[
            'id' => 1, 'categoryId' => null, 'name' => '髪', 'selectionMode' => 'multiple', 'position' => 1,
            'systemManaged' => false,
            'options' => [['id' => 10, 'position' => 1, 'name' => 'Long', 'content' => 'blue eyes, long hair,']],
        ]]);
    }
}
