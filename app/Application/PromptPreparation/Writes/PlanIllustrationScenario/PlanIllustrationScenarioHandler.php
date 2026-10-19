<?php

namespace App\Application\PromptPreparation\Writes\PlanIllustrationScenario;

use App\Application\PromptPreparation\Ports\IllustrationScenarioInstructions;
use App\Application\PromptPreparation\Ports\IllustrationScenarioPlanner;

final readonly class PlanIllustrationScenarioHandler
{
    public function __construct(
        private IllustrationScenarioInstructions $instructions,
        private IllustrationScenarioPlanner $planner,
    ) {
    }

    public function handle(PlanIllustrationScenarioInput $input): PlanIllustrationScenarioResult
    {
        $planned = $this->planner->plan(
            $this->instructions->forPlanning(),
            $input->description,
            $input->sceneCount,
        );

        return new PlanIllustrationScenarioResult(
            $planned->continuityNotes,
            $planned->scenes,
            $planned->model,
            $planned->generationTimeMs,
        );
    }
}
