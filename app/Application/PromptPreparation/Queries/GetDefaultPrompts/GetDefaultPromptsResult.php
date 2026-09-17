<?php

namespace App\Application\PromptPreparation\Queries\GetDefaultPrompts;

final readonly class GetDefaultPromptsResult
{
    public function __construct(
        public string $positive,
        public string $negative,
    ) {
    }
}
