<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class OptionPromptGroup
{
    public string $name;

    public function __construct(
        public ?int $id,
        string $name,
        public OptionSelectionMode $selectionMode,
        public int $position,
    ) {
        $this->name = trim($name);
        if ($this->name === '') {
            throw new InvalidArgumentException('Option prompt group name is required.');
        }
        if ($position < 1) {
            throw new InvalidArgumentException('Option prompt group position must be positive.');
        }
    }
}
