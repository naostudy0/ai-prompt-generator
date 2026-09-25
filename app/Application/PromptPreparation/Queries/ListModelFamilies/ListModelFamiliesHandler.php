<?php

namespace App\Application\PromptPreparation\Queries\ListModelFamilies;

use App\Application\PromptPreparation\Ports\ModelFamilyQueryService;

final readonly class ListModelFamiliesHandler
{
    public function __construct(private ModelFamilyQueryService $families)
    {
    }

    /** @return list<array{id: int, name: string}> */
    public function handle(): array
    {
        return $this->families->all();
    }
}
