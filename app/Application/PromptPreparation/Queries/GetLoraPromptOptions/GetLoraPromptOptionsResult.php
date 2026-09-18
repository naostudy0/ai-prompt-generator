<?php

namespace App\Application\PromptPreparation\Queries\GetLoraPromptOptions;

final readonly class GetLoraPromptOptionsResult
{
    /**
     * @param list<array{id: int, name: string, fileName: string, recommendedStrength: int|float, tags: list<string>}> $loras
     * @param list<array{id: int, loraId: int, name: string, content: string}> $triggers
     * @param list<array{id: int, loraId: int, name: string, content: string}> $outfits
     */
    public function __construct(
        public array $loras,
        public array $triggers,
        public array $outfits,
    ) {
    }
}
