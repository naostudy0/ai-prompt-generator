<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\DefaultPromptRecord;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_再実行しても人物LoRAの変更済み系統と他系統の文面を保持する(): void
    {
        $familyId = DB::table('model_families')->insertGetId([
            'name' => 'anima',
            'normalized_name' => 'anima',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positive = 'anima quality,';
        DB::table('default_prompts')->insert([
            'model_family_id' => $familyId,
            'polarity' => PromptPolarity::Positive->value,
            'content' => $positive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $loraId = DB::table('loras')->insertGetId([
            'name' => '人物',
            'file_name' => 'person.safetensors',
            'recommended_strength_step' => 10,
            'kind' => 'character',
            'model_family_id' => $familyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('loras', ['id' => $loraId, 'model_family_id' => $familyId]);
        $this->assertDatabaseHas('default_prompts', [
            'model_family_id' => $familyId,
            'polarity' => PromptPolarity::Positive->value,
            'content' => $positive,
        ]);
    }
}
