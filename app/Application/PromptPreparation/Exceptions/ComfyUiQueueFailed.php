<?php

namespace App\Application\PromptPreparation\Exceptions;

use RuntimeException;
use Throwable;

final class ComfyUiQueueFailed extends RuntimeException
{
    public function __construct(string $message, public readonly bool $resultUnknown = false, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
