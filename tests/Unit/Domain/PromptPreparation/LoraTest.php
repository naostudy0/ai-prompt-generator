<?php

namespace Tests\Unit\Domain\PromptPreparation;

use App\Domain\PromptPreparation\Models\Lora\Lora;
use App\Domain\PromptPreparation\Models\Lora\LoraFileName;
use App\Domain\PromptPreparation\Models\Lora\LoraStrength;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LoraTest extends TestCase
{
    public function test_登録名が空白だけのLoRAは作成できない(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Lora(
            id: null,
            name: '   ',
            fileName: new LoraFileName('character.safetensors'),
            recommendedStrength: LoraStrength::fromNumber(1),
        );
    }
    public function test_ファイル名と強度からLoRAタグを作る(): void
    {
        $fileName = 'character-v1.safetensors';
        $strength = 0.8;
        $expected = '<lora:character-v1.safetensors:0.8>,';
        $lora = new Lora(null, 'キャラクター', new LoraFileName($fileName), LoraStrength::fromNumber(1));

        $actual = $lora->tag(LoraStrength::fromNumber($strength))->value();

        $this->assertSame($expected, $actual);
    }

    public function test_強度1を小数点なしのLoRAタグにする(): void
    {
        $fileName = 'character';
        $expected = '<lora:character:1>,';
        $lora = new Lora(null, 'キャラクター', new LoraFileName($fileName), LoraStrength::fromNumber(1));

        $actual = $lora->tag(LoraStrength::fromNumber(1))->value();

        $this->assertSame($expected, $actual);
    }

    public function test_0から1までの0点1刻みでない強度は作成できない(): void
    {
        foreach ([-0.1, 0.15, 1.1] as $invalidStrength) {
            try {
                LoraStrength::fromNumber($invalidStrength);
                $this->fail("{$invalidStrength}を強度として作成できてしまいました。");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_タグの構造を壊すファイル名は登録できない(): void
    {
        foreach (['', 'name:0.8', '<name>', 'name,other', "name\nother"] as $invalidFileName) {
            try {
                new LoraFileName($invalidFileName);
                $this->fail("{$invalidFileName}をファイル名として作成できてしまいました。");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
