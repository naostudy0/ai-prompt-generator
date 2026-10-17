<?php

namespace App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt;

enum PromptStyle: string
{
    case DanbooruTags = 'danbooru_tags';
    case NaturalLanguage = 'natural_language';
}
