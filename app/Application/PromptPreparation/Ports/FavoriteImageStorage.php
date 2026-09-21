<?php

namespace App\Application\PromptPreparation\Ports;

interface FavoriteImageStorage
{
    public function store(string $sourcePath, string $extension): string;

    public function delete(string $path): void;

    public function quarantine(string $path): string;

    public function restore(string $quarantinedPath, string $originalPath): void;

    public function url(string $path): string;
}
