<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\ClothingLoraOptionQueryService;
use App\Application\PromptPreparation\Queries\GetClothingLoraOptions\GetClothingLoraOptionsResult;
use App\Domain\PromptPreparation\Models\Lora\LoraFileName;
use App\Domain\PromptPreparation\Models\Lora\LoraStrength;
use App\Domain\PromptPreparation\Models\Lora\LoraTag;
use Illuminate\Support\Facades\DB;

final class EloquentClothingLoraOptionQueryService implements ClothingLoraOptionQueryService
{
    public function getAll(): GetClothingLoraOptionsResult
    {
        $loras = array_values(DB::table('loras')
            ->where('kind', 'clothing')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'file_name', 'recommended_strength_step'])
            ->map(function (object $row): array {
                $fileName = new LoraFileName((string) $row->file_name);

                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'fileName' => $fileName->value,
                    'recommendedStrength' => LoraStrength::fromStep((int) $row->recommended_strength_step)->value(),
                    'tags' => array_map(
                        fn (int $step): string => (new LoraTag(
                            $fileName,
                            LoraStrength::fromStep($step),
                        ))->value(),
                        range(0, 10),
                    ),
                ];
            })->all());

        $triggers = array_values(DB::table('lora_triggers')
            ->join('loras', 'loras.id', '=', 'lora_triggers.lora_id')
            ->where('loras.kind', 'clothing')
            ->orderBy('lora_triggers.name')
            ->orderBy('lora_triggers.id')
            ->get([
                'lora_triggers.id',
                'lora_triggers.lora_id',
                'lora_triggers.name',
                'lora_triggers.content',
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'loraId' => (int) $row->lora_id,
                'name' => (string) $row->name,
                'content' => (string) $row->content,
            ])->all());

        return new GetClothingLoraOptionsResult($loras, $triggers);
    }
}
