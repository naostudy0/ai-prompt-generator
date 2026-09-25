<?php

namespace App\Application\PromptPreparation\Writes\SaveDefaultPrompt;

use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;

final readonly class SaveDefaultPromptHandler
{
    public function __construct(
        private DefaultPromptRepository $repository,
        private ModelFamilyRepository $families,
    ) {
    }

    public function handle(SaveDefaultPromptInput $input): SaveDefaultPromptResult
    {
        $text = PromptText::fromInput($input->content);
        $this->families->ensureExists($input->modelFamilyId);
        $defaultPrompt = new DefaultPrompt($input->polarity, $text, $input->modelFamilyId);

        $this->repository->save($defaultPrompt);

        return new SaveDefaultPromptResult(
            polarity: $input->polarity,
            content: $text->value,
            formatSucceeded: $text->formatSucceeded,
        );
    }
}
