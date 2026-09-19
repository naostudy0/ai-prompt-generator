<?php

namespace App\Application\PromptPreparation\Writes\SaveLora;

use App\Domain\PromptPreparation\Models\Lora\Lora;
use App\Domain\PromptPreparation\Models\Lora\LoraFileName;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Models\Lora\LoraStrength;
use App\Domain\PromptPreparation\Repositories\LoraRepository;
use LogicException;

final readonly class SaveLoraHandler
{
    public function __construct(private LoraRepository $repository)
    {
    }

    public function handle(SaveLoraInput $input, LoraKind $kind): SaveLoraResult
    {
        $saved = $this->repository->save(new Lora(
            id: $input->id,
            name: $input->name,
            fileName: new LoraFileName($input->fileName),
            recommendedStrength: LoraStrength::fromNumber($input->recommendedStrength),
            kind: $kind,
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved LoRA must have an ID.');
        }

        return new SaveLoraResult(
            id: $saved->id,
            name: $saved->name,
            fileName: $saved->fileName->value,
            recommendedStrength: $saved->recommendedStrength->value(),
        );
    }
}
