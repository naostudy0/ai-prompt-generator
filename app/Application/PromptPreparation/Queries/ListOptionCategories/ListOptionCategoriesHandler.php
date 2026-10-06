<?php

namespace App\Application\PromptPreparation\Queries\ListOptionCategories;

use App\Application\PromptPreparation\Ports\OptionCategoryQueryService;

final readonly class ListOptionCategoriesHandler
{
    public function __construct(private OptionCategoryQueryService $queryService)
    {
    }

    /** @return list<array{id: int, name: string, position: int}> */
    public function handle(): array
    {
        return $this->queryService->all();
    }
}
