<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\Lora\LoraTrigger;

interface LoraTriggerRepository
{
    public function save(LoraTrigger $trigger): LoraTrigger;

    public function delete(int $id): void;
}
