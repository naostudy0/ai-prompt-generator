<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class ActionPrompt
{
    public string $name;

    public function __construct(
        public ?int $id,
        string $name,
        public PromptText $content,
    ) {
        $this->name = trim($name);

        if ($this->name === '' || $content->value === '') {
            throw new InvalidArgumentException('Action name and content are required.');
        }
    }
}
