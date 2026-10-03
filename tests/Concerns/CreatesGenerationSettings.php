<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait CreatesGenerationSettings
{
    /** @return array{checkpointOptionId: int, samplerOptionId: int, schedulerOptionId: int} */
    private function generationSettings(int $modelFamilyId = 1): array
    {
        $ids = [];
        foreach ([
            'checkpoint' => 'test-checkpoint.safetensors',
            'sampler' => 'euler_ancestral',
            'scheduler' => 'normal',
        ] as $kind => $value) {
            $id = DB::table('generation_setting_options')
                ->where('model_family_id', $modelFamilyId)->where('kind', $kind)->where('value', $value)
                ->value('id');
            $ids[$kind] = is_numeric($id) ? (int) $id : DB::table('generation_setting_options')->insertGetId([
                'model_family_id' => $modelFamilyId,
                'kind' => $kind,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'checkpointOptionId' => $ids['checkpoint'],
            'samplerOptionId' => $ids['sampler'],
            'schedulerOptionId' => $ids['scheduler'],
        ];
    }
}
