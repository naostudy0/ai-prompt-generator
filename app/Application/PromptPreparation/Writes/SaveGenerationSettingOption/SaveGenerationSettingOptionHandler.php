<?php

namespace App\Application\PromptPreparation\Writes\SaveGenerationSettingOption;

use App\Domain\PromptPreparation\Models\GenerationSettingKind;
use App\Domain\PromptPreparation\Models\GenerationSettingOption;
use App\Domain\PromptPreparation\Repositories\GenerationSettingOptionRepository;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;

final readonly class SaveGenerationSettingOptionHandler
{
    public function __construct(
        private ModelFamilyRepository $families,
        private GenerationSettingOptionRepository $options,
    ) {
    }

    /** @return array{id: int, modelFamilyId: int, kind: string, value: string} */
    public function handle(?int $id, int $modelFamilyId, GenerationSettingKind $kind, string $value): array
    {
        $this->families->ensureExists($modelFamilyId);
        $saved = $this->options->save(new GenerationSettingOption($id, $modelFamilyId, $kind, $value));

        return [
            'id' => $saved->id ?? throw new \LogicException('Stored generation setting must have an ID.'),
            'modelFamilyId' => $saved->modelFamilyId,
            'kind' => $saved->kind->value,
            'value' => $saved->value,
        ];
    }
}
