<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class OptionPromptGroup
{
    public function __construct(public ?int $id, public int $position)
    {
        if ($position < 1) {
            throw new InvalidArgumentException('Option prompt group position must be positive.');
        }
    }

    public function label(): string
    {
        return 'オプション'.$this->position;
    }
}
