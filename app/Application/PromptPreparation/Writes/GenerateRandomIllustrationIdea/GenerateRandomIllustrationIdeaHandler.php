<?php

namespace App\Application\PromptPreparation\Writes\GenerateRandomIllustrationIdea;

use App\Application\PromptPreparation\Ports\RandomIllustrationIdeaGenerator;
use App\Application\PromptPreparation\Ports\RandomIllustrationInstructions;
use App\Application\PromptPreparation\Ports\RandomIllustrationSelectionQueryService;
use App\Application\PromptPreparation\Ports\RandomSource;
use Illuminate\Support\Str;

final readonly class GenerateRandomIllustrationIdeaHandler
{
    public function __construct(private RandomIllustrationSelectionQueryService $selections, private RandomSource $random, private RandomIllustrationInstructions $instructions, private RandomIllustrationIdeaGenerator $generator)
    {
    }

    public function handle(GenerateRandomIllustrationIdeaInput $input): GenerateRandomIllustrationIdeaResult
    {
        $fixed = $this->selections->resolveFixedSelections($input->fixedSelections);
        $fixedLocation = collect($fixed)->first(static fn ($item): bool => $item->location);
        $anchor = $fixedLocation === null ? $this->random->pick($this->selections->locationCandidates()) : null;
        $idea = $this->generator->generate($this->instructions->generation(), $fixed, $anchor);

        return new GenerateRandomIllustrationIdeaResult((string) Str::uuid(), $idea->description, array_map(static fn ($item): array => $item->summary(), $fixed), $anchor?->anchor(), $idea->model, $idea->generationTimeMs);
    }
}
