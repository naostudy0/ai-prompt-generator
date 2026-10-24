<?php

namespace App\Application\PromptPreparation\Writes\ChangeCharacterLoraVisibility;

final readonly class ChangeCharacterLoraVisibilityInput
{
    public function __construct(public int $id, public bool $isHidden)
    {
    }
}
