<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Models\Lora\LoraTrigger;

interface LoraTriggerRepository
{
    public function save(LoraTrigger $trigger, LoraKind $kind): LoraTrigger;

    public function delete(int $id, LoraKind $kind): void;
}
