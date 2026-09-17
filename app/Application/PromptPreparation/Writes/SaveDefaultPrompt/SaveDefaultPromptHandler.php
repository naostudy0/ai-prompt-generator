<?php

namespace App\Application\PromptPreparation\Writes\SaveDefaultPrompt;

use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;

final readonly class SaveDefaultPromptHandler
{
    public function __construct(
        private DefaultPromptRepository $repository,
    ) {
    }

    public function handle(SaveDefaultPromptInput $input): SaveDefaultPromptResult
    {
        $text = PromptText::fromInput($input->content);
        $defaultPrompt = new DefaultPrompt($input->polarity, $text);

        $this->repository->save($defaultPrompt);

        return new SaveDefaultPromptResult(
            polarity: $input->polarity,
            content: $text->value,
            formatSucceeded: $text->formatSucceeded,
        );
    }
}
