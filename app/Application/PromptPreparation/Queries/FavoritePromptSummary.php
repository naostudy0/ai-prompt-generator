<?php

namespace App\Application\PromptPreparation\Queries;

final readonly class FavoritePromptSummary
{
    /** @param list<array{key: string, label: string, items: list<array{label: string, meta: string, details: list<string>}>}> $selectionSummary */
    public function __construct(
        public int $id,
        public ?string $name,
        public string $displayName,
        public array $selectionSummary,
        public ?string $imageUrl,
        public string $createdAt,
    ) {
    }
}
