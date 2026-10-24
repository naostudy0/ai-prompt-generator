<?php

namespace App\Application\PromptPreparation\Writes\ChangeCharacterLoraVisibility;

use App\Domain\PromptPreparation\Models\Lora\LoraKind;
use App\Domain\PromptPreparation\Repositories\LoraRepository;
use LogicException;

final readonly class ChangeCharacterLoraVisibilityHandler
{
    public function __construct(private LoraRepository $repository)
    {
    }

    public function handle(ChangeCharacterLoraVisibilityInput $input): ChangeCharacterLoraVisibilityResult
    {
        $saved = $this->repository->save(
            $this->repository->find($input->id, LoraKind::Character)->withVisibility($input->isHidden),
        );
        if ($saved->id === null) {
            throw new LogicException('The saved LoRA must have an ID.');
        }

        return new ChangeCharacterLoraVisibilityResult($saved->id, $saved->isHidden);
    }
}
