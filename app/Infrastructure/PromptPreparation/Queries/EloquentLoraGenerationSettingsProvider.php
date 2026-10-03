<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Exceptions\InvalidLoraGenerationSelection;
use App\Application\PromptPreparation\Ports\LoraGenerationSettingsProvider;
use Illuminate\Support\Facades\DB;

final class EloquentLoraGenerationSettingsProvider implements LoraGenerationSettingsProvider
{
    public function getForCharacterLora(int $loraId, int $modelFamilyId): array
    {
        $row = DB::table('loras')
            ->join('generation_setting_options as checkpoint', 'checkpoint.id', '=', 'loras.checkpoint_option_id')
            ->join('generation_setting_options as sampler', 'sampler.id', '=', 'loras.sampler_option_id')
            ->join('generation_setting_options as scheduler', 'scheduler.id', '=', 'loras.scheduler_option_id')
            ->where('loras.id', $loraId)->where('loras.kind', 'character')->where('loras.model_family_id', $modelFamilyId)
            ->first(['loras.name', 'checkpoint.value as checkpoint', 'sampler.value as sampler', 'scheduler.value as scheduler']);
        if ($row === null) {
            throw new InvalidLoraGenerationSelection('選択した人物LoRAは対象の系統に属していません。');
        }

        return [
            'checkpoint' => (string) $row->checkpoint,
            'sampler' => (string) $row->sampler,
            'scheduler' => (string) $row->scheduler,
            'name' => (string) $row->name,
        ];
    }
}
