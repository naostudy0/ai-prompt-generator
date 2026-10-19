<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class ScenarioIllustrationPrompt
{
    public function __construct(public string $id, public string $positivePrompt, public string $negativePrompt)
    {
    }
}
