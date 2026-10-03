<?php

namespace App\Application\PromptPreparation\Ports;

interface LoraGenerationSettingsProvider
{
    /** @return array{checkpoint: string, sampler: string, scheduler: string, name: string} */
    public function getForCharacterLora(int $loraId, int $modelFamilyId): array;
}
