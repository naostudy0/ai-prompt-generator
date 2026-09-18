<?php

namespace App\Application\PromptPreparation\Writes\SaveLocationPrompt;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptResult;
use App\Domain\PromptPreparation\Models\LocationPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\LocationPromptRepository;
use LogicException;

final readonly class SaveLocationPromptHandler
{
    public function __construct(private LocationPromptRepository $repository)
    {
    }

    public function handle(SaveNamedPromptInput $input): SaveNamedPromptResult
    {
        $saved = $this->repository->save(new LocationPrompt(
            id: $input->id,
            name: $input->name,
            content: PromptText::fromInput($input->content),
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved location prompt must have an ID.');
        }

        return new SaveNamedPromptResult(
            id: $saved->id,
            name: $saved->name,
            content: $saved->content->value,
            formatSucceeded: $saved->content->formatSucceeded,
        );
    }
}
