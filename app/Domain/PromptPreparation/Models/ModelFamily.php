<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class ModelFamily
{
    public const ILLUSTRIOUS_ID = 1;

    public string $name;

    public string $normalizedName;

    public function __construct(string $name)
    {
        $this->name = trim($name);
        if ($this->name === '') {
            throw new InvalidArgumentException('Model family name is required.');
        }
        $this->normalizedName = mb_strtolower($this->name);
    }
}
