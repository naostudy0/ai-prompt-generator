<?php

namespace App\Domain\PromptPreparation\Models\Lora;

enum LoraKind: string
{
    case Character = 'character';
    case Clothing = 'clothing';
}
