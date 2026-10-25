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
        $consumed = [];
        $review = ['partial' => [], 'conflicts' => [], 'unregistered' => [], 'loras' => []];
        $defaultSelection = [
            'positive' => $this->consumeFull($this->tokens($defaults->positive), $positiveTokens, $consumed),
            'negative' => $this->containsAll($this->tokens($defaults->negative), $negativeTokens),
        ];

        $optionIds = [];
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
                $optionIds[] = $option['id'];
                $this->consumeFull($this->tokens($option['content']), $positiveTokens, $consumed);
            }
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

    /** @param list<string> $required @param list<string> $available */
    private function containsAll(array $required, array $available): bool
    {
        return $required !== [] && array_diff($required, $available) === [];
    }

    /** @param list<string> $required @param list<string> $available @param array<string, bool> $consumed */
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

    /** @param list<array<string, mixed>> $items @return array<string, mixed>|null */
    private function findByFileName(array $items, string $fileName): ?array
    {
        $matches = array_values(array_filter($items, static fn (array $item): bool => $item['fileName'] === $fileName));

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function supportedStrength(float $strength): bool
    {
        return $strength >= 0 && $strength <= 1 && abs($strength * 10 - round($strength * 10)) < 0.000001;
    }

    /** @param array{raw: string, fileName: string, strength: float} $tag
     *  @return array{kind: string, label: string, content: string, registeredId: ?int}
     */
    private function loraReview(string $kind, array $tag, ?int $registeredId = null): array
    {
        return (new ImportedPromptReviewItem($kind, $tag['fileName'], $tag['raw'], $registeredId))->toArray();
    }
}
