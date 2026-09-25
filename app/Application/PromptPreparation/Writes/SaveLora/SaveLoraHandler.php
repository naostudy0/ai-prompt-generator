<?php

namespace App\Application\PromptPreparation\Writes\SaveLora;

use App\Domain\PromptPreparation\Models\Lora\Lora;
use App\Domain\PromptPreparation\Models\Lora\LoraFileName;
use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Models\Lora\LoraStrength;
use App\Domain\PromptPreparation\Repositories\LoraRepository;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;
use LogicException;
use InvalidArgumentException;

final readonly class SaveLoraHandler
{
    public function __construct(private LoraRepository $repository, private ModelFamilyRepository $families)
    {
    }

    public function handle(SaveLoraInput $input, LoraKind $kind): SaveLoraResult
    {
        if ($kind === LoraKind::Character) {
            if ($input->modelFamilyId === null) {
                throw new InvalidArgumentException('Character LoRA model family is required.');
            }
            $this->families->ensureExists($input->modelFamilyId);
        }
        $saved = $this->repository->save(new Lora(
            id: $input->id,
            name: $input->name,
            fileName: new LoraFileName($input->fileName),
            recommendedStrength: LoraStrength::fromNumber($input->recommendedStrength),
            kind: $kind,
            modelFamilyId: $kind === LoraKind::Character ? $input->modelFamilyId : null,
        ));

        if ($saved->id === null) {
            throw new LogicException('The saved LoRA must have an ID.');
        }

        return new SaveLoraResult(
            id: $saved->id,
            name: $saved->name,
            fileName: $saved->fileName->value,
            recommendedStrength: $saved->recommendedStrength->value(),
            modelFamilyId: $saved->modelFamilyId,
        );
    }
}
