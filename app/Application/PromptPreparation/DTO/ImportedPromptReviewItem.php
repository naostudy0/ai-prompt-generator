<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class ImportedPromptReviewItem
{
    public function __construct(
        public string $kind,
        public string $label,
        public string $content,
        public ?int $registeredId = null,
    ) {
    }

    /** @return array{kind: string, label: string, content: string, registeredId: ?int} */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'label' => $this->label, 'content' => $this->content, 'registeredId' => $this->registeredId];
    }
}
