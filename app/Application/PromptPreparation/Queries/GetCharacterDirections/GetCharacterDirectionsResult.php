<?php

namespace App\Application\PromptPreparation\Queries\GetCharacterDirections;

final readonly class GetCharacterDirectionsResult
{
    /**
     * @param list<array{id: int, name: string, content: string}> $expressions
     * @param list<array{id: int, name: string, content: string}> $gazes
     */
    public function __construct(public array $expressions, public array $gazes)
    {
    }
}
