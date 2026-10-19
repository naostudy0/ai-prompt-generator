<?php

namespace App\Application\PromptPreparation\Writes\PlanIllustrationScenario;

final readonly class PlanIllustrationScenarioInput
{
    public function __construct(
        public string $description,
        public int $sceneCount,
    ) {
    }
}
