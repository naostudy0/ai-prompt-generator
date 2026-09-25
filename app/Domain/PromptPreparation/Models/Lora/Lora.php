<?php

namespace App\Domain\PromptPreparation\Models\Lora;

use App\Domain\PromptPreparation\Models\ModelFamily;
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
        public ?int $modelFamilyId = ModelFamily::ILLUSTRIOUS_ID,
    ) {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new InvalidArgumentException('LoRA name is required.');
        }
        if (($kind === LoraKind::Character && $modelFamilyId === null)
            || ($kind === LoraKind::Clothing && $modelFamilyId !== null)) {
            throw new InvalidArgumentException('LoRA kind and model family are inconsistent.');
        }

        $this->name = $trimmed;
    }

    public function tag(LoraStrength $strength): LoraTag
    {
        return new LoraTag($this->fileName, $strength);
    }
}
