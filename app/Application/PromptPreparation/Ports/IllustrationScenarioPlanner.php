<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\PlannedIllustrationScenario;

interface IllustrationScenarioPlanner
{
    public function plan(string $systemPrompt, string $description, int $sceneCount): PlannedIllustrationScenario;
}
