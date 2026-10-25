<?php

namespace App\Application\PromptPreparation\Ports;

use App\Application\PromptPreparation\DTO\ComfyUiImageMetadata;

interface ComfyUiImageMetadataReader
{
    public function read(string $temporaryPath): ComfyUiImageMetadata;
}
