<?php

namespace App\Application\PromptPreparation\Writes\SaveFavoritePrompt;

use App\Application\PromptPreparation\Ports\FavoriteImageStorage;
use App\Application\PromptPreparation\Writes\SaveFavoritePrompt\Exceptions\FavoritePromptNotFound;
use App\Domain\PromptPreparation\Models\FavoritePrompt;
use App\Domain\PromptPreparation\Repositories\FavoritePromptRepository;
use Throwable;

final readonly class SaveFavoritePromptHandler
{
    public function __construct(
        private FavoritePromptRepository $repository,
        private FavoriteImageStorage $images,
    ) {
    }

    public function handle(SaveFavoritePromptInput $input): FavoritePrompt
    {
        $existing = $input->id === null ? null : $this->repository->get($input->id);
        if ($input->id !== null && $existing === null) {
            throw new FavoritePromptNotFound('Favorite prompt was not found.');
        }

        $newImagePath = null;
        try {
            if ($input->imageSourcePath !== null && $input->imageExtension !== null) {
                $newImagePath = $this->images->store($input->imageSourcePath, $input->imageExtension);
            }

            $saved = $this->repository->save(new FavoritePrompt(
                id: $input->id,
                name: $input->name,
                positivePrompt: $input->positivePrompt,
                negativePrompt: $input->negativePrompt,
                selectionSnapshot: $input->selectionSnapshot,
                selectionSummary: $input->selectionSummary,
                imagePath: $newImagePath ?? ($input->removeImage ? null : $existing?->imagePath),
                imageOriginalName: $newImagePath === null
                    ? ($input->removeImage ? null : $existing?->imageOriginalName)
                    : $input->imageOriginalName,
                imageMimeType: $newImagePath === null
                    ? ($input->removeImage ? null : $existing?->imageMimeType)
                    : $input->imageMimeType,
                imageSize: $newImagePath === null
                    ? ($input->removeImage ? null : $existing?->imageSize)
                    : $input->imageSize,
            ));
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                $this->images->delete($newImagePath);
            }
            throw $exception;
        }

        if ($existing?->imagePath !== null && ($newImagePath !== null || $input->removeImage)) {
            try {
                $this->images->delete($existing->imagePath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $saved;
    }
}
