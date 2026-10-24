<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\LoraPromptOptionQueryService;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsResult;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\CharacterLoraVisibility;
use App\Domain\PromptPreparation\Models\Lora\LoraFileName;
use App\Domain\PromptPreparation\Models\Lora\LoraStrength;
use App\Domain\PromptPreparation\Models\Lora\LoraTag;
use Illuminate\Support\Facades\DB;

final class EloquentLoraPromptOptionQueryService implements LoraPromptOptionQueryService
{
    public function getAll(CharacterLoraVisibility $visibility): GetLoraPromptOptionsResult
    {
        $loraQuery = DB::table('loras')->where('kind', 'character');
        if ($visibility !== CharacterLoraVisibility::All) {
            $loraQuery->where('is_hidden', $visibility === CharacterLoraVisibility::Hidden);
        }
        $loraRows = $loraQuery
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'file_name', 'recommended_strength_step', 'model_family_id', 'checkpoint_option_id', 'sampler_option_id', 'scheduler_option_id', 'is_hidden']);
        $loraIds = $loraRows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $loras = array_values($loraRows
            ->map(function (object $row): array {
                $fileName = new LoraFileName((string) $row->file_name);

                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'fileName' => $fileName->value,
                    'recommendedStrength' => LoraStrength::fromStep((int) $row->recommended_strength_step)->value(),
                    'modelFamilyId' => (int) $row->model_family_id,
                    'checkpointOptionId' => (int) $row->checkpoint_option_id,
                    'samplerOptionId' => (int) $row->sampler_option_id,
                    'schedulerOptionId' => (int) $row->scheduler_option_id,
                    'isHidden' => (bool) $row->is_hidden,
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
            ->where('loras.kind', 'character')
            ->whereIn('loras.id', $loraIds)
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

        $outfits = array_values(DB::table('outfits')
            ->whereIn('lora_id', $loraIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'lora_id', 'name', 'content'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'loraId' => (int) $row->lora_id,
                'name' => (string) $row->name,
                'content' => (string) $row->content,
            ])->all());

        return new GetLoraPromptOptionsResult($loras, $triggers, $outfits);
    }
}
