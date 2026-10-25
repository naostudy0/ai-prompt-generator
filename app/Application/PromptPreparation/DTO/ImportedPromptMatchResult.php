<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class ImportedPromptMatchResult
{
    /** @param array{partial: list<array<string, mixed>>, conflicts: list<array<string, mixed>>, unregistered: list<array<string, mixed>>, loras: list<array<string, mixed>>} $review */
    public function __construct(public ImportedSelectionSnapshot $selectionSnapshot, public array $review)
    {
    }
}
