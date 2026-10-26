<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class RandomIllustrationOptionCandidate
{
    public function __construct(
        public int $id,
        public int $groupId,
        public string $groupName,
        public string $selectionMode,
        public string $label,
        public string $content,
        public bool $location,
    ) {
    }

    /** @return array{id:int,label:string} */
    public function anchor(): array
    {
        return ['id' => $this->id, 'label' => $this->label];
    }
}
