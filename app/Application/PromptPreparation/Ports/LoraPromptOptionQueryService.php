<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsResult;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\CharacterLoraVisibility;

interface LoraPromptOptionQueryService
{
    public function getAll(CharacterLoraVisibility $visibility): GetLoraPromptOptionsResult;
}
