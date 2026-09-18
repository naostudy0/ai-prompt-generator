<?php

namespace App\Application\PromptPreparation\Writes\SaveGazePrompt;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptResult;
use App\Domain\PromptPreparation\Models\GazePrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\GazePromptRepository;
use LogicException;

final readonly class SaveGazePromptHandler
{
    public function __construct(private GazePromptRepository $repository)
    {
    }

    public function handle(SaveNamedPromptInput $input): SaveNamedPromptResult
    {
        $saved = $this->repository->save(new GazePrompt(
            id: $input->id,
            name: $input->name,
            content: PromptText::fromInput($input->content),
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved gaze prompt must have an ID.');
        }

        return new SaveNamedPromptResult(
            id: $saved->id,
            name: $saved->name,
            content: $saved->content->value,
            formatSucceeded: $saved->content->formatSucceeded,
        );
    }
}
