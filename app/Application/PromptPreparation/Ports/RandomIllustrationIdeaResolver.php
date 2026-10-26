<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\ResolvedRandomIllustrationIdea;

interface RandomIllustrationIdeaResolver
{
    /** @param list<\App\Application\PromptPreparation\DTO\RandomIllustrationFixedSelection> $fixed
     *  @param list<\App\Application\PromptPreparation\DTO\RandomIllustrationOptionCandidate> $candidates
     */
    public function resolve(string $instructions, string $description, array $fixed, array $candidates): ResolvedRandomIllustrationIdea;
}
