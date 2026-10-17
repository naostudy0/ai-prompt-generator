<?php

namespace App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt;

use App\Application\PromptPreparation\Ports\IllustrationPromptGenerator;
use App\Application\PromptPreparation\Ports\IllustrationPromptInstructions;

final readonly class GenerateIllustrationPromptHandler
{
    public function __construct(
        private IllustrationPromptInstructions $instructions,
        private IllustrationPromptGenerator $generator,
    ) {
    }

    public function handle(GenerateIllustrationPromptInput $input): GenerateIllustrationPromptResult
    {
        $generated = $this->generator->generate(
            $this->instructions->for($input->promptStyle),
            $input->description,
        );

        return new GenerateIllustrationPromptResult(
            positivePrompt: $generated->positivePrompt,
            negativePrompt: $generated->negativePrompt,
            model: $generated->model,
            promptStyle: $input->promptStyle->value,
            generationTimeMs: $generated->generationTimeMs,
        );
    }
}
