<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\GeneratedIllustrationPrompt;

interface IllustrationPromptGenerator
{
    public function generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt;
}
