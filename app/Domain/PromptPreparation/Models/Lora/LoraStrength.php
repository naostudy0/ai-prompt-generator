<?php

namespace App\Domain\PromptPreparation\Models\Lora;

use InvalidArgumentException;

final readonly class LoraStrength
{
    private function __construct(public int $step)
    {
    }

    public static function fromNumber(int|float $value): self
    {
        $step = (int) round($value * 10);

        if ($value < 0 || $value > 1 || abs($value * 10 - $step) > 0.00001) {
            throw new InvalidArgumentException('LoRA strength must be between 0 and 1 in 0.1 increments.');
        }

        return new self($step);
    }

    public static function fromStep(int $step): self
    {
        if ($step < 0 || $step > 10) {
            throw new InvalidArgumentException('LoRA strength step must be between 0 and 10.');
        }

        return new self($step);
    }

    public function value(): int|float
    {
        return $this->step === 10 ? 1 : $this->step / 10;
    }

    public function tagValue(): string
    {
        return $this->step === 10 ? '1' : number_format($this->step / 10, 1, '.', '');
    }
}
