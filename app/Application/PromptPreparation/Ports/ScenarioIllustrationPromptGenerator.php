<?php
namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\GeneratedScenarioPrompts;
use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;

interface ScenarioIllustrationPromptGenerator
{
    public function generateAll(string $systemPrompt, string $description, string $continuityNotes, array $scenes, PromptStyle $promptStyle): GeneratedScenarioPrompts;
    public function regenerateOne(string $systemPrompt, string $description, string $continuityNotes, array $scenes, array $currentPrompts, string $targetId, string $instruction, PromptStyle $promptStyle): ScenarioIllustrationPrompt;
}
