<?php

namespace App\Application\PromptPreparation\Exceptions;

use RuntimeException;

final class InvalidIllustrationPromptResponse extends RuntimeException
{
    public function __construct(string $message = '外部LLMの応答形式が不正です。', public readonly string $reason = 'invalid_response')
    {
        parent::__construct($message);
    }
}
