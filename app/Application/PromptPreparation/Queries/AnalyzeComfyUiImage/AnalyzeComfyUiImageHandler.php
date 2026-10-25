<?php

namespace App\Application\PromptPreparation\Queries\AnalyzeComfyUiImage;

use App\Application\PromptPreparation\Ports\ClothingLoraOptionQueryService;
use App\Application\PromptPreparation\Ports\ComfyUiImageMetadataReader;
use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Application\PromptPreparation\Ports\LoraPromptOptionQueryService;
use App\Application\PromptPreparation\Ports\ModelFamilyQueryService;
use App\Application\PromptPreparation\Ports\OptionPromptQueryService;
use App\Application\PromptPreparation\Queries\GetLoraPromptOptions\CharacterLoraVisibility;
use App\Application\PromptPreparation\Services\ComfyUiWorkflowStructureMatcher;
use App\Application\PromptPreparation\Services\ImportedPromptMatcher;

final readonly class AnalyzeComfyUiImageHandler
{
    public function __construct(
        private ComfyUiImageMetadataReader $images,
        private ModelFamilyQueryService $families,
        private ComfyUiWorkflowStore $workflows,
        private DefaultPromptQueryService $defaults,
        private LoraPromptOptionQueryService $characters,
        private ClothingLoraOptionQueryService $clothing,
        private OptionPromptQueryService $options,
        private ComfyUiWorkflowStructureMatcher $workflowMatcher,
        private ImportedPromptMatcher $promptMatcher,
    ) {
    }

    public function handle(string $temporaryPath): AnalyzeComfyUiImageResult
    {
        $metadata = $this->images->read($temporaryPath);
        $families = $this->families->all();
        $registered = [];
        foreach ($families as $family) {
            $workflow = $this->workflows->findWorkflow($family['id']);
            if ($workflow !== null) {
                $registered[] = ['modelFamilyId' => $family['id'], ...$workflow];
            }
        }
        $matched = $this->workflowMatcher->match($metadata->prompt, $registered);
        $options = $this->options->getAll();
        $match = $this->promptMatcher->match(
            $matched->positivePrompt,
            $matched->negativePrompt,
            $matched->modelFamilyId,
            $this->defaults->get($matched->modelFamilyId),
            $this->characters->getAll(CharacterLoraVisibility::All),
            $this->clothing->getAll(),
            $options,
        );
        $family = array_values(array_filter($families, static fn (array $item): bool => $item['id'] === $matched->modelFamilyId))[0];
        $snapshot = $match->selectionSnapshot;

        return new AnalyzeComfyUiImageResult(
            $family,
            $matched->positivePrompt,
            $matched->negativePrompt,
            ['defaults' => $snapshot->defaults, 'lora' => $snapshot->lora, 'clothingLoras' => $snapshot->clothingLoras, 'optionIds' => $snapshot->optionIds],
            $match->review,
            array_values(array_map(
                static fn (array $group): array => ['id' => $group['id'], 'name' => $group['name']],
                array_filter($options->groups, static fn (array $group): bool => ! $group['systemManaged']),
            )),
        );
    }
}
