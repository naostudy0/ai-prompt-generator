<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class PlannedIllustrationScenario
{
    /** @param list<IllustrationScenarioScene> $scenes */
    public function __construct(
        public string $continuityNotes,
        public array $scenes,
        public string $model,
        public int $generationTimeMs,
    ) {
    }
}
