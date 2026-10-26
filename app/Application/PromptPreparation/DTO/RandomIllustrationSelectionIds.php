<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class RandomIllustrationSelectionIds
{
    /** @param list<array{loraId:int,strength:float|int,triggerId:int|null}> $clothingLoras
     *  @param list<int> $optionIds
     */
    public function __construct(
        public bool $positiveDefault = false,
        public bool $negativeDefault = false,
        public int $modelFamilyId = 1,
        public ?int $characterLoraId = null,
        public float $characterStrength = 1.0,
        public ?int $characterTriggerId = null,
        public ?int $characterOutfitId = null,
        public array $clothingLoras = [],
        public array $optionIds = [],
    ) {
    }
}
