<?php

namespace App\Application\PromptPreparation\Queries\GetPromptOptions;

final readonly class GetPromptOptionsResult
{
    /** @param list<array{id: int, position: int, label: string, options: list<array{id: int, name: string, content: string}>}> $groups */
    public function __construct(public array $groups)
    {
    }
}
