<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\DefaultPromptRecord;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_positiveとnegativeの初期文面を登録する(): void
    {
        $expectedPositive = 'masterpiece, best quality, highres,';
        $expectedNegative = 'bad anatomy, bad hands, extra fingers, missing fingers, extra limbs,';

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('default_prompts', 2);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => PromptPolarity::Positive->value,
            'content' => $expectedPositive,
        ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => PromptPolarity::Negative->value,
            'content' => $expectedNegative,
        ]);
    }

    public function test_再実行しても編集済みまたは空にした文面を上書きしない(): void
    {
        $editedPositive = 'edited positive,';
        $emptyNegative = '';
        DefaultPromptRecord::query()->create([
            'polarity' => PromptPolarity::Positive->value,
            'content' => $editedPositive,
        ]);
        DefaultPromptRecord::query()->create([
            'polarity' => PromptPolarity::Negative->value,
            'content' => $emptyNegative,
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('default_prompts', 2);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => PromptPolarity::Positive->value,
            'content' => $editedPositive,
        ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => PromptPolarity::Negative->value,
            'content' => $emptyNegative,
        ]);
    }
}
