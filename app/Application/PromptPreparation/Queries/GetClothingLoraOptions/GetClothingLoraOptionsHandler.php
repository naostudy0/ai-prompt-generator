<?php

namespace App\Application\PromptPreparation\Queries\GetClothingLoraOptions;

use App\Application\PromptPreparation\Ports\ClothingLoraOptionQueryService;

final readonly class GetClothingLoraOptionsHandler
{
    public function __construct(private ClothingLoraOptionQueryService $queryService)
    {
    }

    public function handle(): GetClothingLoraOptionsResult
    {
        return $this->queryService->getAll();
    }
}
