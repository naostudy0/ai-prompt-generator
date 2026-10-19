<?php
namespace App\Application\PromptPreparation\Writes\RegenerateScenarioScenePrompt;

use App\Application\PromptPreparation\Ports\IllustrationScenarioInstructions;
use App\Application\PromptPreparation\Ports\ScenarioIllustrationPromptGenerator;

final readonly class RegenerateScenarioScenePromptHandler
{
    public function __construct(private IllustrationScenarioInstructions $instructions, private ScenarioIllustrationPromptGenerator $generator) {}
    public function handle(RegenerateScenarioScenePromptInput $input): RegenerateScenarioScenePromptResult
    {
        $scene = $this->generator->regenerateOne($this->instructions->forSceneRegeneration($input->promptStyle), $input->description, $input->continuityNotes, $input->scenes, $input->currentPrompts, $input->targetId, $input->instruction, $input->promptStyle);
        return new RegenerateScenarioScenePromptResult($scene, $input->promptStyle->value);
    }
}
