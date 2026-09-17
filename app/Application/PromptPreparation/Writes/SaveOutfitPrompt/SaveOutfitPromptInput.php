<?php

namespace App\Application\PromptPreparation\Writes\SaveOutfitPrompt;

final readonly class SaveOutfitPromptInput
{
    public function __construct(
        public ?int $id,
        public ?int $loraId,
        public string $name,
        public string $content,
    ) {
    }
}
