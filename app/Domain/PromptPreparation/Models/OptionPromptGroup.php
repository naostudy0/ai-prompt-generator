<?php

namespace App\Domain\PromptPreparation\Models;

use App\Domain\PromptPreparation\Exceptions\SystemManagedOptionPromptGroup;
use InvalidArgumentException;

final readonly class OptionPromptGroup
{
    public string $name;

    public function __construct(
        public ?int $id,
        string $name,
        public OptionSelectionMode $selectionMode,
        public int $position,
        public ?int $categoryId = null,
        public ?string $systemKey = null,
        public ?RandomGenerationRole $randomGenerationRole = null,
    ) {
        $this->name = trim($name);
        if ($this->name === '') {
            throw new InvalidArgumentException('Option prompt group name is required.');
        }
        if ($position < 1) {
            throw new InvalidArgumentException('Option prompt group position must be positive.');
        }
        if ($systemKey !== null && $randomGenerationRole !== null) {
            throw new InvalidArgumentException('System-managed groups cannot have random generation roles.');
        }
    }

    public function changeCategory(?int $categoryId): self
    {
        $this->assertUserManageable();

        return new self(
            $this->id,
            $this->name,
            $this->selectionMode,
            $this->position,
            $categoryId,
            $this->systemKey,
            $this->randomGenerationRole,
        );
    }

    public function isSystemManaged(): bool
    {
        return $this->systemKey !== null;
    }

    public function assertUserManageable(): void
    {
        if ($this->isSystemManaged()) {
            throw new SystemManagedOptionPromptGroup();
        }
    }
}
