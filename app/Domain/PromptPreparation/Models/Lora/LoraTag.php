<?php

namespace App\Domain\PromptPreparation\Models\Lora;

final readonly class LoraTag
{
    public function __construct(
        private LoraFileName $fileName,
        private LoraStrength $strength,
    ) {
    }

    public function value(): string
    {
        return sprintf('<lora:%s:%s>,', $this->fileName->value, $this->strength->tagValue());
    }
}
