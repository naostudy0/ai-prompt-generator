<?php

namespace App\Domain\PromptPreparation\Models\Lora;

use InvalidArgumentException;

final readonly class LoraFileName
{
    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '' || preg_match('/[<>:,\r\n]/', $trimmed) === 1) {
            throw new InvalidArgumentException('LoRA file name is invalid.');
        }

        $this->value = $trimmed;
    }
}
