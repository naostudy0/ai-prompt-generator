<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\Lora\Lora;

interface LoraRepository
{
    public function save(Lora $lora): Lora;

    public function delete(int $id): void;
}
