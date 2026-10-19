<?php
namespace App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt;

use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;

final readonly class RegenerateScenarioScenePromptInput
{
    public function __construct(public string $description, public string $continuityNotes, public array $scenes, public array $currentPrompts, public string $targetId, public string $instruction, public PromptStyle $promptStyle) {}
}
