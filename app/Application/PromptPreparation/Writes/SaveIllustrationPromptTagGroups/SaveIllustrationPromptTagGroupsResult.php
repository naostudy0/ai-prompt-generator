<?php

namespace App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups;

final readonly class SaveIllustrationPromptTagGroupsResult
{
    public function __construct(
        public int $groupId,
        public int $savedCount,
        public int $duplicateCount,
    ) {
    }
}
