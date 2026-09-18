<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\GetPromptOptions\GetPromptOptionsResult;

interface OptionPromptQueryService
{
    public function getAll(): GetPromptOptionsResult;
}
