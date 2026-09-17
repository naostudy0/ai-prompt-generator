<?php

namespace App\Application\PromptPreparation\Writes\SaveLora;

final readonly class SaveLoraResult
{
    public function __construct(
        public int $id,
        public string $name,
        public string $fileName,
        public int|float $recommendedStrength,
    ) {
    }
}
