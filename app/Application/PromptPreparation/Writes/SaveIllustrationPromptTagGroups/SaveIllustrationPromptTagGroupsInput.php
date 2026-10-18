<?php

namespace App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups;

final readonly class SaveIllustrationPromptTagGroupsInput
{
    /** @param list<IllustrationPromptTagGroupInput> $groups */
    public function __construct(public array $groups)
    {
    }
}
