<?php

namespace App\Application\PromptPreparation\Writes\SaveOutfitPrompt;

final readonly class SaveOutfitPromptResult
{
    public function __construct(
        public int $id,
        public ?int $loraId,
        public string $name,
        public string $content,
        public bool $formatSucceeded,
    ) {
    }
}
