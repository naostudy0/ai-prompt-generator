<?php

namespace App\Domain\PromptPreparation\Models\Lora;

use InvalidArgumentException;

final readonly class Lora
{
    public string $name;

    public function __construct(
        public ?int $id,
        string $name,
        public LoraFileName $fileName,
        public LoraStrength $recommendedStrength,
        public LoraKind $kind = LoraKind::Character,
    ) {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new InvalidArgumentException('LoRA name is required.');
        }

        $this->name = $trimmed;
    }

    public function tag(LoraStrength $strength): LoraTag
    {
        return new LoraTag($this->fileName, $strength);
    }
}
