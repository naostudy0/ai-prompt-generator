<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class OptionPrompt
{
    public string $name;

    public function __construct(
        public ?int $id,
        public int $groupId,
        string $name,
        public PromptText $content,
    ) {
        $this->name = trim($name);

        if ($groupId < 1) {
            throw new InvalidArgumentException('Option prompt group ID must be positive.');
        }

        if ($this->name === '' || $content->value === '') {
            throw new InvalidArgumentException('Option name and content are required.');
        }
    }
}
