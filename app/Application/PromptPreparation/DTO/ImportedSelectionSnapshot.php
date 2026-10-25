<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class ImportedSelectionSnapshot
{
    /**
     * @param array{positive: bool, negative: bool} $defaults
     * @param array{loraId: ?int, strength: int|float, triggerId: ?int, outfitId: ?int} $lora
     * @param list<array{loraId: int, strength: int|float, triggerId: ?int}> $clothingLoras
     * @param list<int> $optionIds
     */
    public function __construct(
        public array $defaults,
        public array $lora,
        public array $clothingLoras,
        public array $optionIds,
    ) {
    }
}
