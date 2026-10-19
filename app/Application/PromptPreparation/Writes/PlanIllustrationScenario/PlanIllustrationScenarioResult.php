<?php

namespace App\Application\PromptPreparation\Writes\PlanIllustrationScenario;

use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;

final readonly class PlanIllustrationScenarioResult
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
