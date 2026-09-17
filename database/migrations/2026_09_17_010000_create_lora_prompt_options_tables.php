<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE loras (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name TEXT NOT NULL,
                file_name TEXT NOT NULL UNIQUE,
                recommended_strength_step INTEGER NOT NULL DEFAULT 10
                    CHECK (recommended_strength_step BETWEEN 0 AND 10),
                created_at DATETIME,
                updated_at DATETIME
            )
            SQL);

        Schema::create('lora_triggers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lora_id')->constrained('loras')->cascadeOnDelete();
            $table->text('name');
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('outfits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lora_id')->nullable()->constrained('loras')->nullOnDelete();
            $table->text('name');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfits');
        Schema::dropIfExists('lora_triggers');
        Schema::dropIfExists('loras');
    }
};
