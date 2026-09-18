<?php

namespace App\Application\PromptPreparation\Writes\SaveExpressionPrompt;

use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptInput;
use App\Application\PromptPreparation\Writes\SaveNamedPrompt\SaveNamedPromptResult;
use App\Domain\PromptPreparation\Models\ExpressionPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\ExpressionPromptRepository;
use LogicException;

final readonly class SaveExpressionPromptHandler
{
    public function __construct(private ExpressionPromptRepository $repository)
    {
    }

    public function handle(SaveNamedPromptInput $input): SaveNamedPromptResult
    {
        $saved = $this->repository->save(new ExpressionPrompt(
            id: $input->id,
            name: $input->name,
            content: PromptText::fromInput($input->content),
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved expression prompt must have an ID.');
        }

        return new SaveNamedPromptResult(
            id: $saved->id,
            name: $saved->name,
            content: $saved->content->value,
            formatSucceeded: $saved->content->formatSucceeded,
        );
    }
}
