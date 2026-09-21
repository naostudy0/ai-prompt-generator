<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\FavoritePrompt;
use App\Domain\PromptPreparation\Repositories\FavoritePromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\FavoritePromptRecord;

final class EloquentFavoritePromptRepository implements FavoritePromptRepository
{
    public function get(int $id): ?FavoritePrompt
    {
        $record = FavoritePromptRecord::query()->find($id);

        return $record === null ? null : $this->toDomain($record);
    }

    public function save(FavoritePrompt $favorite): FavoritePrompt
    {
        $record = $favorite->id === null
            ? new FavoritePromptRecord()
            : FavoritePromptRecord::query()->findOrFail($favorite->id);
        $record->fill([
            'name' => $favorite->name,
            'positive_prompt' => $favorite->positivePrompt,
            'negative_prompt' => $favorite->negativePrompt,
            'selection_snapshot' => $favorite->selectionSnapshot,
            'selection_summary' => $favorite->selectionSummary,
            'image_path' => $favorite->imagePath,
            'image_original_name' => $favorite->imageOriginalName,
            'image_mime_type' => $favorite->imageMimeType,
            'image_size' => $favorite->imageSize,
        ])->save();

        return $this->toDomain($record);
    }

    public function delete(int $id): void
    {
        FavoritePromptRecord::query()->whereKey($id)->delete();
    }

    private function toDomain(FavoritePromptRecord $record): FavoritePrompt
    {
        return new FavoritePrompt(
            id: (int) $record->getKey(),
            name: $record->getAttribute('name'),
            positivePrompt: (string) $record->getAttribute('positive_prompt'),
            negativePrompt: (string) $record->getAttribute('negative_prompt'),
            selectionSnapshot: $record->getAttribute('selection_snapshot'),
            selectionSummary: $record->getAttribute('selection_summary'),
            imagePath: $record->getAttribute('image_path'),
            imageOriginalName: $record->getAttribute('image_original_name'),
            imageMimeType: $record->getAttribute('image_mime_type'),
            imageSize: $record->getAttribute('image_size'),
        );
    }
}
