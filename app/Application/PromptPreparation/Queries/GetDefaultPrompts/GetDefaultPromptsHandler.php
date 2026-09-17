<?php

namespace App\Application\PromptPreparation\Queries\GetDefaultPrompts;

use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;

final readonly class GetDefaultPromptsHandler
{
    public function __construct(
        private DefaultPromptQueryService $queryService,
    ) {
    }

    public function handle(): GetDefaultPromptsResult
    {
        return $this->queryService->get();
    }
}
