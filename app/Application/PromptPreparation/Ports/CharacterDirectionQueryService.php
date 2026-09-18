<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\GetCharacterDirections\GetCharacterDirectionsResult;

interface CharacterDirectionQueryService
{
    public function getAll(): GetCharacterDirectionsResult;
}
