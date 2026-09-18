<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class OutfitPrompt
{
    public string $name;

    public function __construct(
        public ?int $id,
        public int $loraId,
        string $name,
        public PromptText $content,
    ) {
        $trimmed = trim($name);

        if ($loraId < 1 || $trimmed === '' || $content->value === '') {
            throw new InvalidArgumentException('Outfit name and content are required.');
        }

        $this->name = $trimmed;
    }
}
