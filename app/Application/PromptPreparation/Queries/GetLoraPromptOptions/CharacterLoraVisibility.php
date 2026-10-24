<?php

namespace App\Application\PromptPreparation\Queries\GetLoraPromptOptions;

enum CharacterLoraVisibility: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
    case All = 'all';
}
