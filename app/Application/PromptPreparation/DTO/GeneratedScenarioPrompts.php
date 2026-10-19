<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class GeneratedScenarioPrompts
{
    /** @param list<ScenarioIllustrationPrompt> $scenes */
    public function __construct(public array $scenes, public string $model, public int $generationTimeMs)
    {
    }
}
