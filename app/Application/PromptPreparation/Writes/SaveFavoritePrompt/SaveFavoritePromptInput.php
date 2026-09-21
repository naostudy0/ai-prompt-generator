<?php

namespace App\Application\PromptPreparation\Writes\SaveFavoritePrompt;

final readonly class SaveFavoritePromptInput
{
    /**
     * @param array<string, mixed> $selectionSnapshot
     * @param list<array<string, mixed>> $selectionSummary
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public string $positivePrompt,
        public string $negativePrompt,
        public array $selectionSnapshot,
        public array $selectionSummary,
        public ?string $imageSourcePath,
        public ?string $imageOriginalName,
        public ?string $imageMimeType,
        public ?int $imageSize,
        public ?string $imageExtension,
        public bool $removeImage,
    ) {
    }
}
