<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\CharacterLoraVisibility;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsResult;

interface LoraPromptOptionQueryService
{
    public function getAll(CharacterLoraVisibility $visibility): GetLoraPromptOptionsResult;
}
