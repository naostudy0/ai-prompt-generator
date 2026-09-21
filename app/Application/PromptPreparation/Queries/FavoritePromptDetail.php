<?php

namespace App\Application\PromptPreparation\Queries;

final readonly class FavoritePromptDetail
{
    /**
     * @param array<string, mixed> $selectionSnapshot
     * @param list<array{key: string, label: string, items: list<array{label: string, meta: string, details: list<string>}>}> $selectionSummary
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public string $displayName,
        public string $positivePrompt,
        public string $negativePrompt,
        public array $selectionSnapshot,
        public array $selectionSummary,
        public ?string $imageUrl,
        public ?string $imageOriginalName,
        public ?string $imageMimeType,
        public ?int $imageSize,
        public string $createdAt,
    ) {
    }
}
