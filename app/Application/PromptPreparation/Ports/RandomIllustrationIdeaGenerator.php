<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\RandomIllustrationIdea;
use App\Application\PromptPreparation\DTO\RandomIllustrationOptionCandidate;

interface RandomIllustrationIdeaGenerator
{
    /** @param list<\App\Application\PromptPreparation\DTO\RandomIllustrationFixedSelection> $fixed */
    public function generate(string $systemPrompt, array $fixed, ?RandomIllustrationOptionCandidate $anchor): RandomIllustrationIdea;
}
