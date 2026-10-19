<?php

namespace App\Application\PromptPreparation\Writes\GenerateScenarioPrompts;

use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;

final readonly class GenerateScenarioPromptsResult
{
    /** @param list<ScenarioIllustrationPrompt> $scenes */
    public function __construct(public array $scenes, public string $model, public string $promptStyle, public int $generationTimeMs)
    {
    }
}
