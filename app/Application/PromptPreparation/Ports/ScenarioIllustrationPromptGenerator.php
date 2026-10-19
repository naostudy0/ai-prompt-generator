<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\GeneratedScenarioPrompts;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;

interface ScenarioIllustrationPromptGenerator
{
    /** @param list<\App\Application\PromptPreparation\DTO\IllustrationScenarioScene> $scenes */
    public function generateAll(string $systemPrompt, string $description, string $continuityNotes, array $scenes, PromptStyle $promptStyle): GeneratedScenarioPrompts;

    /**
     * @param list<\App\Application\PromptPreparation\DTO\IllustrationScenarioScene> $scenes
     * @param list<ScenarioIllustrationPrompt> $currentPrompts
     */
    public function regenerateOne(string $systemPrompt, string $description, string $continuityNotes, array $scenes, array $currentPrompts, string $targetId, string $instruction, PromptStyle $promptStyle): ScenarioIllustrationPrompt;
}
