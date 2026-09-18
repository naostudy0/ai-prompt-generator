<?php

namespace App\Application\PromptPreparation\Writes\SaveActionPrompt;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptResult;
use App\Domain\PromptPreparation\Models\ActionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\ActionPromptRepository;
use LogicException;

final readonly class SaveActionPromptHandler
{
    public function __construct(private ActionPromptRepository $repository)
    {
    }

    public function handle(SaveNamedPromptInput $input): SaveNamedPromptResult
    {
        $saved = $this->repository->save(new ActionPrompt(
            id: $input->id,
            name: $input->name,
            content: PromptText::fromInput($input->content),
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved action prompt must have an ID.');
        }

        return new SaveNamedPromptResult(
            id: $saved->id,
            name: $saved->name,
            content: $saved->content->value,
            formatSucceeded: $saved->content->formatSucceeded,
        );
    }
}
