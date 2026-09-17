<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\LoraPromptOptionQueryService;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsResult;
use App\Domain\PromptPreparation\Models\Lora\LoraFileName;
use App\Domain\PromptPreparation\Models\Lora\LoraStrength;
use App\Domain\PromptPreparation\Models\Lora\LoraTag;
use Illuminate\Support\Facades\DB;

final class EloquentLoraPromptOptionQueryService implements LoraPromptOptionQueryService
{
    public function getAll(): GetLoraPromptOptionsResult
    {
        $loras = array_values(DB::table('loras')
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
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'lora_id', 'name', 'content'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'loraId' => (int) $row->lora_id,
                'name' => (string) $row->name,
                'content' => (string) $row->content,
            ])->all());

        $outfits = array_values(DB::table('outfits')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'lora_id', 'name', 'content'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'loraId' => $row->lora_id === null ? null : (int) $row->lora_id,
                'name' => (string) $row->name,
                'content' => (string) $row->content,
            ])->all());

        return new GetLoraPromptOptionsResult($loras, $triggers, $outfits);
    }
}
