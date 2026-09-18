<?php

namespace App\Domain\PromptPreparation\Models;

enum OptionSelectionMode: string
{
    case Single = 'single';
    case Multiple = 'multiple';
}
