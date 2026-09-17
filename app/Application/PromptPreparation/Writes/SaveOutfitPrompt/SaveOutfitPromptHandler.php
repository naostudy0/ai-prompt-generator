<?php

namespace App\Application\PromptPreparation\Writes\SaveOutfitPrompt;

use App\Domain\PromptPreparation\Models\OutfitPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\OutfitPromptRepository;
use LogicException;

final readonly class SaveOutfitPromptHandler
{
    public function __construct(private OutfitPromptRepository $repository)
    {
    }

    public function handle(SaveOutfitPromptInput $input): SaveOutfitPromptResult
    {
        $saved = $this->repository->save(new OutfitPrompt(
            id: $input->id,
            loraId: $input->loraId,
            name: $input->name,
            content: PromptText::fromInput($input->content),
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved outfit must have an ID.');
        }

        return new SaveOutfitPromptResult(
            id: $saved->id,
            loraId: $saved->loraId,
            name: $saved->name,
            content: $saved->content->value,
            formatSucceeded: $saved->content->formatSucceeded,
        );
    }
}
