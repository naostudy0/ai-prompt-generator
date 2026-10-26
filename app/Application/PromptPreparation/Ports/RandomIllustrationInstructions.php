<?php

namespace App\Application\PromptPreparation\Ports;

interface RandomIllustrationInstructions
{
    public function generation(): string;
    public function resolution(): string;
}
