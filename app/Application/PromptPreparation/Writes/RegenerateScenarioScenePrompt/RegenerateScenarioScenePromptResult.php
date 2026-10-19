<?php
namespace App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt;

use App\Application\PromptPreparation\DTO\ScenarioIllustrationPrompt;

final readonly class RegenerateScenarioScenePromptResult
{
    public function __construct(public ScenarioIllustrationPrompt $scene, public string $promptStyle) {}
}
