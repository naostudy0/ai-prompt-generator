<?php

namespace App\Application\PromptPreparation\Queries\ListGenerationSettingOptions;

use App\Domain\PromptPreparation\Repositories\GenerationSettingOptionRepository;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;

final readonly class ListGenerationSettingOptionsHandler
{
    public function __construct(
        private ModelFamilyRepository $families,
        private GenerationSettingOptionRepository $options,
    ) {
    }

    /** @return list<array{id: int, modelFamilyId: int, kind: string, value: string}> */
    public function handle(int $modelFamilyId): array
    {
        $this->families->ensureExists($modelFamilyId);

        return array_map(static fn ($option): array => [
            'id' => $option->id ?? throw new \LogicException('Stored generation setting must have an ID.'),
            'modelFamilyId' => $option->modelFamilyId,
            'kind' => $option->kind->value,
            'value' => $option->value,
        ], $this->options->listForFamily($modelFamilyId));
    }
}
