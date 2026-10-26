<?php

namespace App\Application\PromptPreparation\Writes\GenerateRandomIllustrationIdea;

final readonly class GenerateRandomIllustrationIdeaResult
{
    /**
     * @param  list<array{key:string,label:string}>  $fixedSelectionSummaries
     * @param  array{id:int,label:string}|null  $locationAnchor
     */
    public function __construct(public string $ideaId, public string $description, public array $fixedSelectionSummaries, public ?array $locationAnchor, public string $model, public int $generationTimeMs)
    {
    }
}
