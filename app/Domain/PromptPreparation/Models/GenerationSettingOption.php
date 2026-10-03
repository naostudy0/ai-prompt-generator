<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class GenerationSettingOption
{
    public string $value;

    public function __construct(
        public ?int $id,
        public int $modelFamilyId,
        public GenerationSettingKind $kind,
        string $value,
    ) {
        $this->value = trim($value);
        if ($this->value === '') {
            throw new InvalidArgumentException('Generation setting value is required.');
        }
    }
}
