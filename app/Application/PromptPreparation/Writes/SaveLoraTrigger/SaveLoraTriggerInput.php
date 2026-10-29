<?php

namespace App\Application\PromptPreparation\Writes\SaveLoraTrigger;

final readonly class SaveLoraTriggerInput
{
    public function __construct(
        public ?int $id,
        public int $loraId,
        public string $name,
        public string $content,
        public ?string $negativeContent = null,
        public bool $hasNegativeContent = false,
    ) {
    }
}
