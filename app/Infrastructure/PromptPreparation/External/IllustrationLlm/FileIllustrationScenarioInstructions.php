<?php

namespace App\Infrastructure\PromptPreparation\External\IllustrationLlm;

use App\Application\PromptPreparation\Ports\IllustrationScenarioInstructions;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use RuntimeException;

final class FileIllustrationScenarioInstructions implements IllustrationScenarioInstructions
{
    public function forPlanning(): string
    {
        return $this->read('scenario_plan.txt');
    }
    public function forPromptStyle(PromptStyle $style): string
    {
        return $this->read('scenario_'.$style->value.'.txt');
    }
    public function forSceneRegeneration(PromptStyle $style): string
    {
        return $this->read('scenario_scene_'.$style->value.'.txt');
    }
    private function read(string $filename): string
    {
        $content = @file_get_contents(resource_path('prompts/illustration/'.$filename));
        if ($content === false || trim($content) === '') {
            throw new RuntimeException("Scenario instruction is unreadable or empty: {$filename}");
        }
        return trim($content);
    }
}
