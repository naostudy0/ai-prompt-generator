<?php

namespace App\Infrastructure\PromptPreparation\Queries;

use App\Application\PromptPreparation\Ports\FavoriteImageStorage;
use App\Application\PromptPreparation\Ports\FavoritePromptQueryService;
use App\Application\PromptPreparation\Queries\FavoritePromptDetail;
use App\Application\PromptPreparation\Queries\FavoritePromptSummary;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\FavoritePromptRecord;
use Carbon\CarbonInterface;

final readonly class EloquentFavoritePromptQueryService implements FavoritePromptQueryService
{
    public function __construct(private FavoriteImageStorage $images)
    {
    }

    public function list(): array
    {
        return array_values(FavoritePromptRecord::query()->latest()->get()->map(
            fn (FavoritePromptRecord $record): FavoritePromptSummary => $this->mapSummary($record),
        )->all());
    }

    public function find(int $id): ?FavoritePromptDetail
    {
        $record = FavoritePromptRecord::query()->find($id);

        if ($record === null) {
            return null;
        }

        $summary = $this->mapSummary($record);

        return new FavoritePromptDetail(
            id: $summary->id,
            name: $summary->name,
            displayName: $summary->displayName,
            positivePrompt: $record->getAttribute('positive_prompt'),
            negativePrompt: $record->getAttribute('negative_prompt'),
            selectionSnapshot: $record->getAttribute('selection_snapshot'),
            selectionSummary: $summary->selectionSummary,
            imageUrl: $summary->imageUrl,
            imageOriginalName: $record->getAttribute('image_original_name'),
            imageMimeType: $record->getAttribute('image_mime_type'),
            imageSize: $record->getAttribute('image_size'),
            createdAt: $summary->createdAt,
        );
    }

    private function mapSummary(FavoritePromptRecord $record): FavoritePromptSummary
    {
        $createdAt = $record->getAttribute('created_at');
        $createdAtText = $createdAt instanceof CarbonInterface ? $createdAt->toAtomString() : '';

        return new FavoritePromptSummary(
            id: (int) $record->getKey(),
            name: $record->getAttribute('name'),
            displayName: $record->getAttribute('name') ?? sprintf(
                '%sのお気に入り',
                $createdAt instanceof CarbonInterface ? $createdAt->format('Y/m/d H:i') : '',
            ),
            selectionSummary: $record->getAttribute('selection_summary'),
            imageUrl: ($path = $record->getAttribute('image_path')) === null
                ? null : $this->images->url($path),
            createdAt: $createdAtText,
        );
    }
}
