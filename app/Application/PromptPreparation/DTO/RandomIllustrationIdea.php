<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class RandomIllustrationIdea
{
    public function __construct(public string $description, public string $model, public int $generationTimeMs)
    {
    }
}
