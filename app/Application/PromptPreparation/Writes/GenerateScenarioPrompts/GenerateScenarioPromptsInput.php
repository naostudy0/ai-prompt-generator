<?php
namespace App\Application\PromptPreparation\Writes\GenerateScenarioPrompts;

use App\Application\PromptPreparation\DTO\IllustrationScenarioScene;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;

final readonly class GenerateScenarioPromptsInput
{
    /** @param list<IllustrationScenarioScene> $scenes */
    public function __construct(public string $description, public string $continuityNotes, public array $scenes, public PromptStyle $promptStyle) {}
}
