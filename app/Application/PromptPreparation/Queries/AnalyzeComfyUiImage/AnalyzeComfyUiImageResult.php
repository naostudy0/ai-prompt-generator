<?php

namespace App\Application\PromptPreparation\Queries\AnalyzeComfyUiImage;

final readonly class AnalyzeComfyUiImageResult
{
    /**
     * @param array{id: int, name: string} $modelFamily
     * @param array<string, mixed> $selectionSnapshot
     * @param array<string, list<array<string, mixed>>> $review
     * @param list<array{id: int, name: string}> $optionGroups
     */
    public function __construct(
        public array $modelFamily,
        public string $positivePrompt,
        public string $negativePrompt,
        public array $selectionSnapshot,
        public array $review,
        public array $optionGroups,
    ) {
    }
}
