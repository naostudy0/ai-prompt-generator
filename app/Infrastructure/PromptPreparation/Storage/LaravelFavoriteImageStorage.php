<?php

namespace App\Infrastructure\PromptPreparation\Storage;

use App\Application\PromptPreparation\Ports\FavoriteImageStorage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class LaravelFavoriteImageStorage implements FavoriteImageStorage
{
    public function store(string $sourcePath, string $extension): string
    {
        $path = sprintf('favorite-prompts/%s.%s', str()->uuid(), $extension);
        $stream = fopen($sourcePath, 'rb');
        if ($stream === false || ! Storage::disk('public')->put($path, $stream)) {
            throw new RuntimeException('Favorite image could not be stored.');
        }
        fclose($stream);

        return $path;
    }

    public function delete(string $path): void
    {
        if (Storage::disk('public')->exists($path) && ! Storage::disk('public')->delete($path)) {
            throw new RuntimeException('Favorite image could not be deleted.');
        }
    }

    public function quarantine(string $path): string
    {
        $quarantinedPath = sprintf('favorite-prompts/.deleting/%s', str()->uuid());
        if (! Storage::disk('public')->move($path, $quarantinedPath)) {
            throw new RuntimeException('Favorite image could not be prepared for deletion.');
        }

        return $quarantinedPath;
    }

    public function restore(string $quarantinedPath, string $originalPath): void
    {
        if (! Storage::disk('public')->move($quarantinedPath, $originalPath)) {
            throw new RuntimeException('Favorite image could not be restored.');
        }
    }

    public function url(string $path): string
    {
        return Storage::disk('public')->url($path);
    }
}
