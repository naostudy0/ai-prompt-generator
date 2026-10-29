<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['option_prompts', 'outfits', 'lora_triggers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->text('negative_content')->default('');
            });
        }
    }

    public function down(): void
    {
        foreach (['option_prompts', 'outfits', 'lora_triggers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('negative_content');
            });
        }
    }
};
