<?php

namespace App\Application\PromptPreparation\Ports;

interface ModelFamilyQueryService
{
    /** @return list<array{id: int, name: string}> */
    public function all(): array;
}
