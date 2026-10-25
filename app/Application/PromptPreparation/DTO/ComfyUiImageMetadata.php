<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class ComfyUiImageMetadata
{
    /** @param array<string, mixed> $prompt */
    public function __construct(public array $prompt)
    {
    }
}
