<?php

namespace Database\Seeders;

use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\DefaultPromptRecord;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $initialPrompts = [
            PromptPolarity::Positive->value => 'masterpiece, best quality, highres,',
            PromptPolarity::Negative->value => 'bad anatomy, bad hands, extra fingers, missing fingers, extra limbs,',
        ];

        foreach ($initialPrompts as $polarity => $content) {
            $text = PromptText::fromInput($content);

            DefaultPromptRecord::query()->firstOrCreate(
                ['polarity' => $polarity],
                ['content' => $text->value],
            );
        }
    }
}
