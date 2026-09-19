<?php

namespace App\Application\PromptPreparation\Writes\SaveLoraTrigger;

use App\Domain\PromptPreparation\Models\Lora\LoraTrigger;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;
use LogicException;

final readonly class SaveLoraTriggerHandler
{
    public function __construct(private LoraTriggerRepository $repository)
    {
    }

    public function handle(
        SaveLoraTriggerInput $input,
        LoraKind $kind,
    ): SaveLoraTriggerResult {
        $saved = $this->repository->save(new LoraTrigger(
            id: $input->id,
            loraId: $input->loraId,
            name: $input->name,
            content: PromptText::fromInput($input->content),
        ), $kind);

        if ($saved->id === null) {
            throw new LogicException('The saved LoRA trigger must have an ID.');
        }

        return new SaveLoraTriggerResult(
            id: $saved->id,
            loraId: $saved->loraId,
            name: $saved->name,
            content: $saved->content->value,
            formatSucceeded: $saved->content->formatSucceeded,
        );
    }
}
