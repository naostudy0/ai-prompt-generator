<?php

namespace App\Application\PromptPreparation\DTO;

final readonly class TransientPromptSuggestion
{
    public function __construct(public string $polarity, public string $label, public string $tags)
    {
    }

    /** @return array{polarity:string,label:string,tags:string} */
    public function toArray(): array
    {
        return ['polarity' => $this->polarity, 'label' => $this->label, 'tags' => $this->tags];
    }
}
