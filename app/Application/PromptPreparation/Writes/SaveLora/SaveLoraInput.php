<?php

namespace App\Application\PromptPreparation\Writes\SaveLora;

final readonly class SaveLoraInput
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $fileName,
        public int|float $recommendedStrength,
        public ?int $modelFamilyId = null,
    ) {
    }
}
