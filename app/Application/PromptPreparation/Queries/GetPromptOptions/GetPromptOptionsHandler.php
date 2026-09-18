<?php

namespace App\Application\PromptPreparation\Queries\GetPromptOptions;

use App\Application\PromptPreparation\Ports\OptionPromptQueryService;

final readonly class GetPromptOptionsHandler
{
    public function __construct(private OptionPromptQueryService $queryService)
    {
    }

    public function handle(): GetPromptOptionsResult
    {
        return $this->queryService->getAll();
    }
}
