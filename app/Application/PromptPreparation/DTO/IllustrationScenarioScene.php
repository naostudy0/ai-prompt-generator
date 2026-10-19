<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class IllustrationScenarioScene
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $changesFromPrevious,
    ) {}
}
