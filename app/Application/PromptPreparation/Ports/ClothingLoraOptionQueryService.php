<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Queries\GetClothingLoraOptions\GetClothingLoraOptionsResult;

interface ClothingLoraOptionQueryService
{
    public function getAll(): GetClothingLoraOptionsResult;
}
