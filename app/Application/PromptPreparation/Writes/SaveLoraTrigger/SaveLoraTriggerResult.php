<?php

namespace App\Application\PromptPreparation\Writes\SaveLoraTrigger;

final readonly class SaveLoraTriggerResult
{
    public function __construct(
        public int $id,
        public int $loraId,
        public string $name,
        public string $content,
        public bool $formatSucceeded,
    ) {
    }
}
