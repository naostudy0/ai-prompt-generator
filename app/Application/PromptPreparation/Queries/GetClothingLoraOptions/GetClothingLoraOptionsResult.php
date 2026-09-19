<?php

namespace App\Application\PromptPreparation\Queries\GetClothingLoraOptions;

final readonly class GetClothingLoraOptionsResult
{
    /**
     * @param list<array{id: int, name: string, fileName: string, recommendedStrength: int|float, tags: list<string>}> $loras
     * @param list<array{id: int, loraId: int, name: string, content: string}> $triggers
     */
    public function __construct(public array $loras, public array $triggers)
    {
    }
}
