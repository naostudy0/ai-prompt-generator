<?php

namespace App\Application\PromptPreparation\Queries\GetPromptOptions;

final readonly class GetPromptOptionsResult
{
    /** @param list<array{id: int, name: string, content: string}> $options */
    public function __construct(public array $options)
    {
    }
}
