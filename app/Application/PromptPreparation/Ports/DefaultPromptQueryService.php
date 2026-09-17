<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsResult;

interface DefaultPromptQueryService
{
    public function get(): GetDefaultPromptsResult;
}
