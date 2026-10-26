<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\DTO\RandomIllustrationFixedSelection;
use App\Application\PromptPreparation\DTO\RandomIllustrationOptionCandidate;
use App\Application\PromptPreparation\DTO\RandomIllustrationSelectionIds;
use App\Application\PromptPreparation\Exceptions\InvalidRandomIllustrationSelection;
use App\Application\PromptPreparation\Ports\RandomIllustrationSelectionQueryService;
use Illuminate\Support\Facades\DB;

final class EloquentRandomIllustrationSelectionQueryService implements RandomIllustrationSelectionQueryService
{
    public function resolveFixedSelections(RandomIllustrationSelectionIds $ids): array
    {
        $fixed = [];
        foreach ([['positive', $ids->positiveDefault], ['negative', $ids->negativeDefault]] as [$polarity, $selected]) {
            if (!$selected) {
                continue;
            }
            $row = DB::table('default_prompts')->where('model_family_id', $ids->modelFamilyId)->where('polarity', $polarity)->first(['content']);
            if ($row !== null) {
                $fixed[] = new RandomIllustrationFixedSelection("default:$polarity", "既定の{$polarity}文面", (string) $row->content, 'default');
            }
        }

        if ($ids->characterLoraId !== null) {
            $lora = DB::table('loras')->where('id', $ids->characterLoraId)->where('kind', 'character')->where('is_hidden', false)->first(['id', 'name', 'file_name']);
            if ($lora === null) {
                throw new InvalidRandomIllustrationSelection('選択中の人物LoRAを利用できません。');
            }
            $fixed[] = new RandomIllustrationFixedSelection('character-lora:'.$lora->id, (string) $lora->name, '<lora:'.$lora->file_name.':'.$ids->characterStrength.'>', 'character_lora', (int) $lora->id);
            $this->appendRelated($fixed, 'lora_triggers', $ids->characterTriggerId, (int) $lora->id, 'character-trigger');
            $this->appendRelated($fixed, 'outfits', $ids->characterOutfitId, (int) $lora->id, 'character-outfit');
        } elseif ($ids->characterTriggerId !== null || $ids->characterOutfitId !== null) {
            throw new InvalidRandomIllustrationSelection('人物LoRAの関連選択が不正です。');
        }

        foreach ($ids->clothingLoras as $selection) {
            $row = DB::table('loras')->where('id', $selection['loraId'])->where('kind', 'clothing')->first(['id', 'name', 'file_name']);
            if ($row === null) {
                throw new InvalidRandomIllustrationSelection('選択中の衣装LoRAを利用できません。');
            }
            $fixed[] = new RandomIllustrationFixedSelection('clothing-lora:'.$row->id, (string) $row->name, '<lora:'.$row->file_name.':'.$selection['strength'].'>', 'clothing_lora', (int) $row->id);
            $this->appendRelated($fixed, 'lora_triggers', $selection['triggerId'], (int) $row->id, 'clothing-trigger');
        }

        $requested = array_values(array_unique($ids->optionIds));
        $rows = DB::table('option_prompts')->join('option_prompt_groups', 'option_prompt_groups.id', '=', 'option_prompts.option_prompt_group_id')
            ->whereIn('option_prompts.id', $requested)->get(['option_prompts.id', 'option_prompts.name', 'option_prompts.content', 'option_prompt_groups.id as group_id', 'option_prompt_groups.selection_mode', 'option_prompt_groups.random_generation_role']);
        if ($rows->count() !== count($requested)) {
            throw new InvalidRandomIllustrationSelection('選択中の項目を利用できません。');
        }
        foreach ($rows as $row) {
            $fixed[] = new RandomIllustrationFixedSelection('option:'.$row->id, (string) $row->name, (string) $row->content, 'option', (int) $row->id, (int) $row->group_id, (string) $row->selection_mode, $row->random_generation_role === 'location_source');
        }

        return $fixed;
    }

    public function locationCandidates(): array
    {
        return array_values(array_filter($this->resolutionCandidates(), static fn (RandomIllustrationOptionCandidate $candidate): bool => $candidate->location));
    }

    public function resolutionCandidates(): array
    {
        return array_values(DB::table('option_prompts')->join('option_prompt_groups', 'option_prompt_groups.id', '=', 'option_prompts.option_prompt_group_id')->orderBy('option_prompt_groups.position')->orderBy('option_prompts.position')->get(['option_prompts.id', 'option_prompts.name', 'option_prompts.content', 'option_prompt_groups.id as group_id', 'option_prompt_groups.name as group_name', 'option_prompt_groups.selection_mode', 'option_prompt_groups.random_generation_role'])->map(static fn (object $row): RandomIllustrationOptionCandidate => new RandomIllustrationOptionCandidate((int) $row->id, (int) $row->group_id, (string) $row->group_name, (string) $row->selection_mode, (string) $row->name, (string) $row->content, $row->random_generation_role === 'location_source'))->all());
    }

    /** @param list<RandomIllustrationFixedSelection> $fixed */
    private function appendRelated(array &$fixed, string $table, ?int $id, int $loraId, string $type): void
    {
        if ($id === null) {
            return;
        }
        $row = DB::table($table)->where('id', $id)->where('lora_id', $loraId)->first(['id', 'name', 'content']);
        if ($row === null) {
            throw new InvalidRandomIllustrationSelection('LoRAの関連選択が不正です。');
        }
        $fixed[] = new RandomIllustrationFixedSelection($type.':'.$row->id, (string) $row->name, (string) $row->content, $type, (int) $row->id);
    }
}
