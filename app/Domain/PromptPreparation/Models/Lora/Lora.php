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
        public ?int $checkpointOptionId = null,
        public ?int $samplerOptionId = null,
        public ?int $schedulerOptionId = null,
    ) {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new InvalidArgumentException('LoRA name is required.');
        }
        if ($kind === LoraKind::Character && (
            preg_match('/[<>:"\/\\\\|?*\x00-\x1F]/u', $trimmed) === 1
            || str_ends_with($trimmed, '.')
            || in_array($trimmed, ['.', '..'], true)
        )) {
            throw new InvalidArgumentException('Character LoRA name must be safe for a file name.');
        }
        if (($kind === LoraKind::Character && $modelFamilyId === null)
            || ($kind === LoraKind::Clothing && $modelFamilyId !== null)) {
            throw new InvalidArgumentException('LoRA kind and model family are inconsistent.');
        }
        $settings = [$checkpointOptionId, $samplerOptionId, $schedulerOptionId];
        if (($kind === LoraKind::Character && in_array(null, $settings, true))
            || ($kind === LoraKind::Clothing && !empty(array_filter($settings, static fn (?int $id): bool => $id !== null)))) {
            throw new InvalidArgumentException('LoRA kind and generation settings are inconsistent.');
        }

        $this->name = $trimmed;
    }

    public function tag(LoraStrength $strength): LoraTag
    {
        return new LoraTag($this->fileName, $strength);
    }
}
