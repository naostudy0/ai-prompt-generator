<?php

namespace App\Application\PromptPreparation\Writes\ResolveRandomIllustrationIdea;

use App\Application\PromptPreparation\Exceptions\InvalidIllustrationPromptResponse;
use App\Application\PromptPreparation\Exceptions\RandomIllustrationContextTooLarge;
use App\Application\PromptPreparation\Ports\RandomIllustrationIdeaResolver;
use App\Application\PromptPreparation\Ports\RandomIllustrationInstructions;
use App\Application\PromptPreparation\Ports\RandomIllustrationSelectionQueryService;

final readonly class ResolveRandomIllustrationIdeaHandler
{
    public function __construct(private RandomIllustrationSelectionQueryService $selections, private RandomIllustrationInstructions $instructions, private RandomIllustrationIdeaResolver $resolver)
    {
    }

    public function handle(ResolveRandomIllustrationIdeaInput $input): ResolveRandomIllustrationIdeaResult
    {
        $fixed = $this->selections->resolveFixedSelections($input->fixedSelections);
        $candidates = $this->selections->resolutionCandidates();
        $instructions = $this->instructions->resolution();
        $encoded = json_encode(['description' => $input->description, 'fixed_selections' => $fixed, 'registered_candidates' => $candidates], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $availableBytes = max(0, (int) config('services.illustration_llm.random_input_budget', 200000) - (int) config('services.illustration_llm.random_reserved_bytes', 20000));
        if (strlen($instructions) + strlen($encoded) > $availableBytes) {
            throw new RandomIllustrationContextTooLarge('登録候補が多すぎるため解析できません。');
        }
        $resolved = $this->resolver->resolve($instructions, $input->description, $fixed, $candidates);
        $candidateById = collect($candidates)->keyBy('id');
        $fixedOptions = array_values(array_filter($fixed, static fn ($item): bool => $item->type === 'option'));
        $ids = array_values(array_unique(array_merge(array_map(static fn ($item): int => (int) $item->id, $fixedOptions), $resolved->selectedOptionIds)));
        foreach ($ids as $id) {
            if (!$candidateById->has($id)) {
                throw new InvalidIllustrationPromptResponse('外部LLMが未知の項目を返しました。');
            }
        }
        $singleGroups = [];
        foreach ($ids as $id) {
            $candidate = $candidateById->get($id);
            if ($candidate === null) {
                throw new InvalidIllustrationPromptResponse('外部LLMが未知の項目を返しました。');
            }
            if ($candidate->selectionMode === 'single' && isset($singleGroups[$candidate->groupId])) {
                throw new InvalidIllustrationPromptResponse('外部LLMが単一選択を重複させました。');
            }
            if ($candidate->selectionMode === 'single') {
                $singleGroups[$candidate->groupId] = true;
            }
        }
        if (count($resolved->suggestions) > 10) {
            throw new InvalidIllustrationPromptResponse('外部LLMの一時候補が上限を超えました。');
        }

        return new ResolveRandomIllustrationIdeaResult($input->ideaId, $resolved->resolvedDescription, $ids, $resolved->suggestions, $resolved->adjustments, $resolved->model, $resolved->generationTimeMs);
    }
}
