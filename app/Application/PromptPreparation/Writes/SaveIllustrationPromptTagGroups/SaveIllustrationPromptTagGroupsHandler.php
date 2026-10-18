<?php

namespace App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Models\OptionPromptGroup;
use App\Domain\PromptPreparation\Models\OptionSelectionMode;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use LogicException;

final readonly class SaveIllustrationPromptTagGroupsHandler
{
    private const string SYSTEM_KEY = 'ai_prompt_suggestions';

    public function __construct(
        private OptionPromptGroupRepository $groups,
        private OptionPromptRepository $prompts,
        private TransactionManager $transactions,
    ) {
    }

    public function handle(SaveIllustrationPromptTagGroupsInput $input): SaveIllustrationPromptTagGroupsResult
    {
        return $this->transactions->run(function () use ($input): SaveIllustrationPromptTagGroupsResult {
            $group = $this->groups->findBySystemKeyForUpdate(self::SYSTEM_KEY)
                ?? $this->groups->save(new OptionPromptGroup(
                    null,
                    'AIプロンプト',
                    OptionSelectionMode::Multiple,
                    $this->groups->nextPosition(),
                    null,
                    self::SYSTEM_KEY,
                ));
            if ($group->id === null) {
                throw new LogicException('The saved option prompt group must have an ID.');
            }

            $existing = $this->prompts->getAllInGroupForUpdate($group->id);
            $keys = [];
            foreach ($existing as $prompt) {
                $keys[$this->normalizedTagSetKey($prompt->content->value)] = true;
            }

            $position = count($existing) + 1;
            $savedCount = 0;
            $duplicateCount = 0;
            foreach ($input->groups as $candidate) {
                $key = $this->normalizedTagSetKey($candidate->tags);
                if (isset($keys[$key])) {
                    $duplicateCount++;

                    continue;
                }
                $keys[$key] = true;
                $this->prompts->save(new OptionPrompt(
                    null,
                    $group->id,
                    $candidate->label,
                    PromptText::fromInput($candidate->tags),
                    $position++,
                ));
                $savedCount++;
            }

            return new SaveIllustrationPromptTagGroupsResult(
                $group->id,
                $savedCount,
                $duplicateCount,
            );
        });
    }

    private function normalizedTagSetKey(string $tags): string
    {
        $normalized = array_values(array_filter(array_map(
            static fn (string $tag): string => strtolower(trim($tag)),
            explode(',', $tags),
        ), static fn (string $tag): bool => $tag !== ''));
        sort($normalized);

        return implode("\0", $normalized);
    }
}
