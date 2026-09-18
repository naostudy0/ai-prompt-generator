<?php

namespace App\Application\PromptPreparation\Writes\SaveNamedPrompt;

final readonly class SaveNamedPromptInput
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $content,
    ) {
    }
}
