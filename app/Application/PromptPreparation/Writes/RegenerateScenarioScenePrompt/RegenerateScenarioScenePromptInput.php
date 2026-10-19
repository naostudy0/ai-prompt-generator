<?php

namespace App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt;

use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;

final readonly class RegenerateScenarioScenePromptInput
{
    /**
     * @param list<\App\Application\PromptPreparation\DTO\IllustrationScenarioScene> $scenes
     * @param list<\App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt> $currentPrompts
     */
    public function __construct(public string $description, public string $continuityNotes, public array $scenes, public array $currentPrompts, public string $targetId, public string $instruction, public PromptStyle $promptStyle)
    {
    }
}
