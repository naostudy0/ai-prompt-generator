<?php

namespace App\Domain\PromptPreparation\Models\Lora;

use App\Domain\PromptPreparation\Models\PromptText;
use InvalidArgumentException;

final readonly class LoraTrigger
{
    public string $name;

    public function __construct(
        public ?int $id,
        public int $loraId,
        string $name,
        public PromptText $content,
    ) {
        $trimmed = trim($name);

        if ($trimmed === '' || $content->value === '') {
            throw new InvalidArgumentException('LoRA trigger name and content are required.');
        }

        $this->name = $trimmed;
    }
}
