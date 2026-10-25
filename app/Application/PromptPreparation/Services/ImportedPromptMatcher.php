<?php

namespace App\Application\PromptPreparation\Services;

use App\Application\PromptPreparation\DTO\ImportedPromptMatchResult;
use App\Application\PromptPreparation\DTO\ImportedPromptReviewItem;
use App\Application\PromptPreparation\DTO\ImportedSelectionSnapshot;
use App\Application\PromptPreparation\Queries\GetClothingLoraOptions\GetClothingLoraOptionsResult;
use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsResult;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\GetLoraPromptOptionsResult;
use App\Application\PromptPreparation\Queries\GetPromptOptions\GetPromptOptionsResult;

final class ImportedPromptMatcher
{
    public function match(
        string $positive,
        string $negative,
        int $modelFamilyId,
        GetDefaultPromptsResult $defaults,
        GetLoraPromptOptionsResult $characters,
        GetClothingLoraOptionsResult $clothing,
        GetPromptOptionsResult $options,
    ): ImportedPromptMatchResult {
        $positiveTokens = $this->tokens($positive);
        $negativeTokens = $this->tokens($negative);
        /** @var array<string, bool> $consumed */
        $consumed = [];
        $review = ['partial' => [], 'conflicts' => [], 'unregistered' => [], 'loras' => []];
        $defaultSelection = [
            'positive' => $this->consumeFull($this->tokens($defaults->positive), $positiveTokens, $consumed),
            'negative' => $this->containsAll($this->tokens($defaults->negative), $negativeTokens),
        ];

        $optionIds = [];
        $optionCandidates = [];
        foreach ($options->groups as $group) {
            $full = [];
            foreach ($group['options'] as $option) {
                $tokens = $this->tokens($option['content']);
                if ($this->containsAll($tokens, $positiveTokens)) {
                    $full[] = $option;
                } elseif (array_intersect($tokens, $positiveTokens) !== []) {
                    $review['partial'][] = (new ImportedPromptReviewItem('partial', $option['name'], $option['content'], $option['id']))->toArray();
                }
            }
            if ($group['selectionMode'] === 'single' && count($full) > 1) {
                foreach ($full as $option) {
                    $review['conflicts'][] = (new ImportedPromptReviewItem('conflict', $option['name'], $option['content'], $option['id']))->toArray();
                }
                continue;
            }
            foreach ($full as $option) {
                $optionCandidates[] = $option;
            }
        }
        foreach ($optionCandidates as $index => $option) {
            $tokens = $this->tokens($option['content']);
            $overlaps = array_filter(
                $optionCandidates,
                fn (array $candidate, int $candidateIndex): bool => $candidateIndex !== $index
                    && array_intersect($tokens, $this->tokens($candidate['content'])) !== [],
                ARRAY_FILTER_USE_BOTH,
            );
            if ($overlaps !== []) {
                $review['conflicts'][] = (new ImportedPromptReviewItem('conflict', $option['name'], $option['content'], $option['id']))->toArray();
                continue;
            }
            $optionIds[] = $option['id'];
            $this->consumeFull($tokens, $positiveTokens, $consumed);
        }

        $characterSelection = ['loraId' => null, 'strength' => 1, 'triggerId' => null, 'outfitId' => null];
        $clothingSelections = [];
        foreach ($this->loraTags($positive) as $tag) {
            $consumed[$tag['raw']] = true;
            $character = $this->findByFileName($characters->loras, $tag['fileName']);
            $clothingLora = $this->findByFileName($clothing->loras, $tag['fileName']);
            if ($character !== null && $clothingLora !== null) {
                $review['loras'][] = $this->loraReview('conflict', $tag);
            } elseif ($character !== null) {
                if ($character['isHidden']) {
                    $review['loras'][] = $this->loraReview('hidden_lora', $tag, $character['id']);
                } elseif ($character['modelFamilyId'] !== $modelFamilyId) {
                    $review['loras'][] = $this->loraReview('conflict', $tag, $character['id']);
                } elseif (! $this->supportedStrength($tag['strength'])) {
                    $review['loras'][] = $this->loraReview('unsupported_lora_strength', $tag, $character['id']);
                } elseif ($characterSelection['loraId'] !== null) {
                    $review['loras'][] = $this->loraReview('conflict', $tag, $character['id']);
                } else {
                    $characterSelection['loraId'] = $character['id'];
                    $characterSelection['strength'] = $tag['strength'];
                }
            } elseif ($clothingLora !== null) {
                if (! $this->supportedStrength($tag['strength'])) {
                    $review['loras'][] = $this->loraReview('unsupported_lora_strength', $tag, $clothingLora['id']);
                } else {
                    $clothingSelections[] = ['loraId' => $clothingLora['id'], 'strength' => $tag['strength'], 'triggerId' => null];
                }
            } else {
                $review['loras'][] = $this->loraReview('unknown_lora', $tag);
            }
        }

        if ($characterSelection['loraId'] !== null) {
            $characterSelection['triggerId'] = $this->matchOwnedPrompt(
                $characters->triggers,
                $characterSelection['loraId'],
                $positiveTokens,
                $consumed,
                $review,
            );
            $characterSelection['outfitId'] = $this->matchOwnedPrompt(
                $characters->outfits,
                $characterSelection['loraId'],
                $positiveTokens,
                $consumed,
                $review,
            );
        }
        foreach ($clothingSelections as &$selection) {
            $selection['triggerId'] = $this->matchOwnedPrompt(
                $clothing->triggers,
                $selection['loraId'],
                $positiveTokens,
                $consumed,
                $review,
            );
        }
        unset($selection);

        foreach ($positiveTokens as $token) {
            if (! isset($consumed[$token]) && ! str_starts_with($token, '<lora:')) {
                $review['unregistered'][] = (new ImportedPromptReviewItem('unregistered', $token, $token))->toArray();
            }
        }

        return new ImportedPromptMatchResult(
            new ImportedSelectionSnapshot($defaultSelection, $characterSelection, $clothingSelections, $optionIds),
            $review,
        );
    }

    /** @return list<string> */
    private function tokens(string $content): array
    {
        $tokens = [];
        $current = '';
        $depth = 0;
        $escaped = false;
        foreach (preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($escaped) {
                $current .= $character;
                $escaped = false;
                continue;
            }
            if ($character === '\\') {
                $current .= $character;
                $escaped = true;
                continue;
            }
            $depth += $character === '(' ? 1 : ($character === ')' ? -1 : 0);
            if ($character === ',' && $depth === 0) {
                if (trim($current) !== '') {
                    $tokens[] = trim($current);
                }
                $current = '';
            } else {
                $current .= $character;
            }
        }
        if (trim($current) !== '') {
            $tokens[] = trim($current);
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param list<string> $required
     * @param list<string> $available
     */
    private function containsAll(array $required, array $available): bool
    {
        return $required !== [] && array_diff($required, $available) === [];
    }

    /**
     * @param list<string> $required
     * @param list<string> $available
     * @param array<string, bool> $consumed
     * @param-out array<string, bool> $consumed
     */
    private function consumeFull(array $required, array $available, array &$consumed): bool
    {
        if (! $this->containsAll($required, $available)) {
            return false;
        }
        foreach ($required as $token) {
            $consumed[$token] = true;
        }

        return true;
    }

    /** @return list<array{raw: string, fileName: string, strength: float}> */
    private function loraTags(string $positive): array
    {
        preg_match_all('/<lora:([^:>]+):(-?\d+(?:\.\d+)?)>/', $positive, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => ['raw' => $match[0], 'fileName' => $match[1], 'strength' => (float) $match[2]], $matches);
    }

    /**
     * @param list<array{id: int, name: string, fileName: string, recommendedStrength: int|float, tags: list<string>}>|list<array{id: int, name: string, fileName: string, recommendedStrength: int|float, modelFamilyId: int, checkpointOptionId: int, samplerOptionId: int, schedulerOptionId: int, isHidden: bool, tags: list<string>}> $items
     * @return array<string, mixed>|null
     */
    private function findByFileName(array $items, string $fileName): ?array
    {
        $matches = array_values(array_filter($items, static fn (array $item): bool => $item['fileName'] === $fileName));

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function supportedStrength(float $strength): bool
    {
        return $strength >= 0 && $strength <= 1 && abs($strength * 10 - round($strength * 10)) < 0.000001;
    }

    /**
     * @param list<array{id: int, loraId: int, name: string, content: string}> $items
     * @param list<string> $available
     * @param array<string, bool> $consumed
     * @param array{partial: list<array<string, mixed>>, conflicts: list<array<string, mixed>>, unregistered: list<array<string, mixed>>, loras: list<array<string, mixed>>} $review
     */
    private function matchOwnedPrompt(
        array $items,
        int $loraId,
        array $available,
        array &$consumed,
        array &$review,
    ): ?int {
        $full = [];
        foreach ($items as $item) {
            if ($item['loraId'] !== $loraId) {
                continue;
            }
            $tokens = $this->tokens($item['content']);
            if ($this->containsAll($tokens, $available)) {
                $full[] = $item;
            } elseif (array_intersect($tokens, $available) !== []) {
                $review['partial'][] = (new ImportedPromptReviewItem('partial', $item['name'], $item['content'], $item['id']))->toArray();
            }
        }
        if (count($full) !== 1) {
            foreach ($full as $item) {
                $review['conflicts'][] = (new ImportedPromptReviewItem('conflict', $item['name'], $item['content'], $item['id']))->toArray();
            }

            return null;
        }
        $this->consumeFull($this->tokens($full[0]['content']), $available, $consumed);

        return $full[0]['id'];
    }

    /** @param array{raw: string, fileName: string, strength: float} $tag
     *  @return array{kind: string, label: string, content: string, registeredId: ?int}
     */
    private function loraReview(string $kind, array $tag, ?int $registeredId = null): array
    {
        return (new ImportedPromptReviewItem($kind, $tag['fileName'], $tag['raw'], $registeredId))->toArray();
    }
}
