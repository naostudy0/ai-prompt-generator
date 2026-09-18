<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class CompositionPrompt
{
    public string $name;

    public function __construct(
        public ?int $id,
        string $name,
        public PromptText $content,
    ) {
        $this->name = trim($name);

        if ($this->name === '' || $content->value === '') {
            throw new InvalidArgumentException('Composition name and content are required.');
        }
    }
}
