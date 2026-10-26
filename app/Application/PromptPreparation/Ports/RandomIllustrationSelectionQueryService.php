<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\RandomIllustrationSelectionIds;

interface RandomIllustrationSelectionQueryService
{
    /** @return list<\App\Application\PromptPreparation\DTO\RandomIllustrationFixedSelection> */
    public function resolveFixedSelections(RandomIllustrationSelectionIds $ids): array;

    /** @return list<\App\Application\PromptPreparation\DTO\RandomIllustrationOptionCandidate> */
    public function locationCandidates(): array;

    /** @return list<\App\Application\PromptPreparation\DTO\RandomIllustrationOptionCandidate> */
    public function resolutionCandidates(): array;
}
