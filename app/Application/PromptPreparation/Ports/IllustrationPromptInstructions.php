<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;

interface IllustrationPromptInstructions
{
    public function for(PromptStyle $style): string;
}
