<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\Lora\LoraTrigger;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;

interface LoraTriggerRepository
{
    public function save(LoraTrigger $trigger, LoraKind $kind): LoraTrigger;

    public function delete(int $id, LoraKind $kind): void;
}
