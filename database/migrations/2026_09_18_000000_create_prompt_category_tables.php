<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['expression_prompts', 'gaze_prompts', 'location_prompts', 'composition_prompts', 'action_prompts'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->text('name');
                $table->text('content');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['action_prompts', 'composition_prompts', 'location_prompts', 'gaze_prompts', 'expression_prompts'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
