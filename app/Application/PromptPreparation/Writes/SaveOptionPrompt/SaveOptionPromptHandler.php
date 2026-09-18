<?php

namespace App\Application\PromptPreparation\Writes\SaveOptionPrompt;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Domain\PromptPreparation\Models\OptionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use LogicException;

final readonly class SaveOptionPromptHandler
{
    public function __construct(private OptionPromptRepository $repository)
    {
    }

    /** @return array{id: int, groupId: int, name: string, content: string, formatSucceeded: bool} */
    public function handle(SaveNamedPromptInput $input, ?int $groupId): array
    {
        $groupId ??= $input->id === null ? null : $this->repository->get($input->id)->groupId;
        if ($groupId === null) {
            throw new LogicException('A new option prompt must have a group ID.');
        }

        $saved = $this->repository->save(new OptionPrompt(
            id: $input->id,
            groupId: $groupId,
            name: $input->name,
            content: PromptText::fromInput($input->content),
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved option prompt must have an ID.');
        }

        return ['id' => $saved->id, 'groupId' => $saved->groupId, 'name' => $saved->name,
            'content' => $saved->content->value, 'formatSucceeded' => $saved->content->formatSucceeded];
    }
}
