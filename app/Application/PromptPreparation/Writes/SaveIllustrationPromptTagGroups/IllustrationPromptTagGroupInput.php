<?php

namespace App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups;

final readonly class IllustrationPromptTagGroupInput
{
    public function __construct(
        public string $label,
        public string $tags,
    ) {
    }
}
