<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;

interface IllustrationScenarioInstructions
{
    public function forPlanning(): string;

    public function forPromptStyle(PromptStyle $style): string;

    public function forSceneRegeneration(PromptStyle $style): string;
}
