<?php

namespace App\Domain\PromptPreparation\Models;

use InvalidArgumentException;

final readonly class FavoritePrompt
{
    public ?string $name;

    /**
     * @param array<string, mixed> $selectionSnapshot
     * @param list<array<string, mixed>> $selectionSummary
     */
    public function __construct(
        public ?int $id,
        ?string $name,
        public string $positivePrompt,
        public string $negativePrompt,
        public array $selectionSnapshot,
        public array $selectionSummary,
        public ?string $imagePath = null,
        public ?string $imageOriginalName = null,
        public ?string $imageMimeType = null,
        public ?int $imageSize = null,
    ) {
        $trimmedName = $name === null ? null : trim($name);
        $this->name = $trimmedName === '' ? null : $trimmedName;

        if ($positivePrompt === '' && $negativePrompt === '') {
            throw new InvalidArgumentException('At least one prompt output is required.');
        }
        if ($this->name !== null && mb_strlen($this->name) > 255) {
            throw new InvalidArgumentException('Favorite prompt name must not exceed 255 characters.');
        }
        $imageValues = [$imagePath, $imageOriginalName, $imageMimeType, $imageSize];
        $presentImageValues = array_filter($imageValues, fn (mixed $value): bool => $value !== null);
        if ($presentImageValues !== [] && count($presentImageValues) !== count($imageValues)) {
            throw new InvalidArgumentException('Favorite image metadata must be complete.');
        }
    }
}
