<?php
namespace App\Application\PromptPreparation\Writes\GenerateScenarioPrompts;

use App\Application\PromptPreparation\Ports\IllustrationScenarioInstructions;
use App\Application\PromptPreparation\Ports\ScenarioIllustrationPromptGenerator;

final readonly class GenerateScenarioPromptsHandler
{
    public function __construct(private IllustrationScenarioInstructions $instructions, private ScenarioIllustrationPromptGenerator $generator) {}
    public function handle(GenerateScenarioPromptsInput $input): GenerateScenarioPromptsResult
    {
        $generated = $this->generator->generateAll($this->instructions->forPromptStyle($input->promptStyle), $input->description, $input->continuityNotes, $input->scenes, $input->promptStyle);
        return new GenerateScenarioPromptsResult($generated->scenes, $generated->model, $input->promptStyle->value, $generated->generationTimeMs);
    }
}
