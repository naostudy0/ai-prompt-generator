<?php

namespace App\Application\PromptPreparation\Ports;

interface OptionCategoryQueryService
{
    /** @return list<array{id: int, name: string, position: int}> */
    public function all(): array;
}
