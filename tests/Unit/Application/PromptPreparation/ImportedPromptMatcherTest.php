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
                ['id' => 10, 'position' => 1, 'name' => 'A', 'content' => 'blue eyes,', 'negativeContent' => ''],
                ['id' => 11, 'position' => 2, 'name' => 'B', 'content' => 'blue eyes,', 'negativeContent' => ''],
                ['id' => 12, 'position' => 3, 'name' => 'C', 'content' => 'blue eyes, long hair,', 'negativeContent' => ''],
            ],
        ]]);

        $result = $this->matcher()->match('blue eyes,', '', 1, new GetDefaultPromptsResult('', ''), $this->characters(), $this->clothing(), $options);

        self::assertSame([], $result->selectionSnapshot->optionIds);
        self::assertNotEmpty($result->review['partial']);
        self::assertNotEmpty($result->review['conflicts']);
    }

    public function test_同じタグを消費する別ブロックの項目は自動選択しない(): void
    {
        $groups = [];
        foreach ([1 => '髪', 2 => '人物'] as $id => $name) {
            $groups[] = [
                'id' => $id, 'categoryId' => null, 'name' => $name, 'selectionMode' => 'multiple', 'position' => $id,
                'systemManaged' => false,
                'options' => [['id' => $id * 10, 'position' => 1, 'name' => $name, 'content' => 'long hair,', 'negativeContent' => '']],
            ];
        }

        $result = $this->matcher()->match(
            'long hair,',
            '',
            1,
            new GetDefaultPromptsResult('', ''),
            $this->characters(),
            $this->clothing(),
            new GetPromptOptionsResult($groups),
        );

        self::assertSame([], $result->selectionSnapshot->optionIds);
        self::assertSame([10, 20], array_column($result->review['conflicts'], 'registeredId'));
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
            '',
            1,
            new GetDefaultPromptsResult('', ''),
            $this->characters(),
            $this->clothing(),
            new GetPromptOptionsResult([]),
        );

        self::assertSame(1, $result->selectionSnapshot->lora['loraId']);
        self::assertSame(0.8, $result->selectionSnapshot->lora['strength']);
        self::assertSame([], $result->selectionSnapshot->clothingLoras);
        self::assertSame(['hidden_lora', 'unsupported_lora_strength'], array_column($result->review['loras'], 'kind'));
    }

    public function test_人物と衣装LoRAに所属する全文一致トリガーと服装を復元する(): void
    {
        $characters = new GetLoraPromptOptionsResult(
            $this->characters()->loras,
            [['id' => 21, 'loraId' => 1, 'name' => '人物トリガー', 'content' => 'hero trigger,', 'negativeContent' => '']],
            [['id' => 22, 'loraId' => 1, 'name' => '人物服装', 'content' => 'hero outfit,', 'negativeContent' => '']],
        );
        $clothing = new GetClothingLoraOptionsResult(
            $this->clothing()->loras,
            [['id' => 31, 'loraId' => 3, 'name' => '衣装トリガー', 'content' => 'dress trigger,', 'negativeContent' => '']],
        );

        $result = $this->matcher()->match(
            '<lora:hero.safetensors:0.8>, hero trigger, hero outfit, <lora:dress.safetensors:1>, dress trigger,',
            '',
            1,
            new GetDefaultPromptsResult('', ''),
            $characters,
            $clothing,
            new GetPromptOptionsResult([]),
        );

        self::assertSame(21, $result->selectionSnapshot->lora['triggerId']);
        self::assertSame(22, $result->selectionSnapshot->lora['outfitId']);
        self::assertSame([['loraId' => 3, 'strength' => 1.0, 'triggerId' => 31]], $result->selectionSnapshot->clothingLoras);
        self::assertSame([], $result->review['unregistered']);
    }

    public function test_同じ人物に全文一致するトリガーが複数あれば自動選択しない(): void
    {
        $characters = new GetLoraPromptOptionsResult(
            $this->characters()->loras,
            [
                ['id' => 21, 'loraId' => 1, 'name' => 'A', 'content' => 'hero trigger,', 'negativeContent' => ''],
                ['id' => 22, 'loraId' => 1, 'name' => 'B', 'content' => 'hero trigger,', 'negativeContent' => ''],
            ],
            [],
        );

        $result = $this->matcher()->match(
            '<lora:hero.safetensors:1>, hero trigger,',
            '',
            1,
            new GetDefaultPromptsResult('', ''),
            $characters,
            $this->clothing(),
            new GetPromptOptionsResult([]),
        );

        self::assertNull($result->selectionSnapshot->lora['triggerId']);
        self::assertSame(['conflict', 'conflict'], array_column($result->review['conflicts'], 'kind'));
    }

    private function matcher(): ImportedPromptMatcher
    {
        return new ImportedPromptMatcher();
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
            'options' => [['id' => 10, 'position' => 1, 'name' => 'Long', 'content' => 'blue eyes, long hair,', 'negativeContent' => '']],
        ]]);
    }
}
