<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class RandomIllustrationFixedSelection
{
    public function __construct(
        public string $key,
        public string $label,
        public string $content,
        public string $type,
        public ?int $id = null,
        public ?int $groupId = null,
        public ?string $selectionMode = null,
        public bool $location = false,
    ) {
    }

    /** @return array{key:string,label:string} */
    public function summary(): array
    {
        return ['key' => $this->key, 'label' => $this->label];
    }
}
