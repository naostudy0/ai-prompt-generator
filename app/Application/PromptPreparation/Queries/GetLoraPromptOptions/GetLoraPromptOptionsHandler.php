<?php

namespace App\Application\PromptPreparation\Queries\GetLoraPromptOptions;

use App\Application\PromptPreparation\Ports\LoraPromptOptionQueryService;

final readonly class GetLoraPromptOptionsHandler
{
    public function __construct(private LoraPromptOptionQueryService $queryService)
    {
    }

    public function handle(): GetLoraPromptOptionsResult
    {
        return $this->queryService->getAll();
    }
}
