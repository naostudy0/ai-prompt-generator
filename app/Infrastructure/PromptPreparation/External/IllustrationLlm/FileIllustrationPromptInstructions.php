<?php

namespace App\Infrastructure\PromptPreparation\External\IllustrationLlm;

use App\Application\PromptPreparation\Ports\IllustrationPromptInstructions;
use App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt\PromptStyle;
use RuntimeException;

final class FileIllustrationPromptInstructions implements IllustrationPromptInstructions
{
    public function for(PromptStyle $style): string
    {
        $filename = match ($style) {
            PromptStyle::DanbooruTags => 'danbooru_tags.txt',
            PromptStyle::NaturalLanguage => 'natural_language.txt',
        };
        $path = resource_path("prompts/illustration/{$filename}");
        $content = is_readable($path) ? file_get_contents($path) : false;

        if ($content === false || trim($content) === '') {
            throw new RuntimeException("Illustration prompt instruction is unreadable or empty: {$filename}");
        }

        return trim($content)."\n\n".$this->multipleCharacterRules();
    }

    private function multipleCharacterRules(): string
    {
        $filename = 'multiple_character_prompt_rules.txt';
        $content = is_readable(resource_path("prompts/illustration/{$filename}"))
            ? file_get_contents(resource_path("prompts/illustration/{$filename}"))
            : false;

        if ($content === false || trim($content) === '') {
            throw new RuntimeException("Illustration prompt instruction is unreadable or empty: {$filename}");
        }

        return trim($content);
    }
}
