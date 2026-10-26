<?php

namespace App\Infrastructure\PromptPreparation\External\IllustrationLlm;

use App\Application\PromptPreparation\Ports\RandomIllustrationInstructions;
use RuntimeException;

final class FileRandomIllustrationInstructions implements RandomIllustrationInstructions
{
    public function generation(): string
    {
        return $this->read('random_idea.txt');
    }

    public function resolution(): string
    {
        return $this->read('random_resolve.txt');
    }

    private function read(string $file): string
    {
        $content = file_get_contents(resource_path('prompts/illustration/'.$file));
        if ($content === false || trim($content) === '') {
            throw new RuntimeException('Random illustration instruction is unreadable or empty.');
        }

        return trim($content);
    }
}
