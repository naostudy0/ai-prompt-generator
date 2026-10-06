<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class OptionCategory
{
    public string $name;

    public function __construct(public ?int $id, string $name, public int $position)
    {
        $this->name = trim($name);
        if ($this->name === '') {
            throw new InvalidArgumentException('Option category name is required.');
        }
        if ($position < 1) {
            throw new InvalidArgumentException('Option category position must be positive.');
        }
    }
}
