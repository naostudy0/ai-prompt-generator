<?php

namespace App\Application\PromptPreparation\Writes\SaveOptionPrompt;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use LogicException;

final readonly class SaveOptionPromptHandler
{
    public function __construct(
        private OptionPromptRepository $repository,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{id: int, groupId: int, position: int, name: string, content: string, formatSucceeded: bool} */
    public function handle(SaveNamedPromptInput $input, ?int $groupId): array
    {
        $saved = $this->transactions->run(function () use ($input, $groupId): OptionPrompt {
            $existing = $input->id === null ? null : $this->repository->get($input->id);
            $resolvedGroupId = $groupId ?? $existing?->groupId;
            if ($resolvedGroupId === null) {
                throw new LogicException('A new option prompt must have a group ID.');
            }

            return $this->repository->save(new OptionPrompt(
                id: $input->id,
                groupId: $resolvedGroupId,
                name: $input->name,
                content: PromptText::fromInput($input->content),
                position: $existing === null
                    ? $this->repository->nextPosition($resolvedGroupId)
                    : $existing->position,
            ));
        });

        if ($saved->id === null) {
            throw new LogicException('The saved option prompt must have an ID.');
        }

        return ['id' => $saved->id, 'groupId' => $saved->groupId, 'position' => $saved->position, 'name' => $saved->name,
            'content' => $saved->content->value, 'formatSucceeded' => $saved->content->formatSucceeded];
    }
}
