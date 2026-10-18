<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class ReusableIllustrationTagGroup
{
    public function __construct(
        public string $label,
        public string $tags,
    ) {
    }
}
