<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\Lora\Lora;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;

interface LoraRepository
{
    public function save(Lora $lora): Lora;

    public function ensureKind(int $id, LoraKind $kind): void;

    public function delete(int $id, LoraKind $kind): void;
}
