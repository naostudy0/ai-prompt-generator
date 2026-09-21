<?php

namespace App\Application\PromptPreparation\Writes\DeleteFavoritePrompt;

use App\Application\PromptPreparation\Ports\FavoriteImageStorage;
use App\Domain\PromptPreparation\Repositories\FavoritePromptRepository;
use Throwable;

final readonly class DeleteFavoritePromptHandler
{
    public function __construct(
        private FavoritePromptRepository $repository,
        private FavoriteImageStorage $images,
    ) {
    }

    public function handle(int $id): bool
    {
        $favorite = $this->repository->get($id);
        if ($favorite === null) {
            return false;
        }

        $quarantinedImage = $favorite->imagePath === null
            ? null : $this->images->quarantine($favorite->imagePath);
        try {
            $this->repository->delete($id);
        } catch (Throwable $exception) {
            if ($quarantinedImage !== null) {
                $this->images->restore($quarantinedImage, $favorite->imagePath);
            }
            throw $exception;
        }
        if ($quarantinedImage !== null) {
            try {
                $this->images->delete($quarantinedImage);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return true;
    }
}
