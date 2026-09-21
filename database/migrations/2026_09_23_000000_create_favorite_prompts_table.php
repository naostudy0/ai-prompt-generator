<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('favorite_prompts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->text('positive_prompt');
            $table->text('negative_prompt');
            $table->json('selection_snapshot');
            $table->json('selection_summary');
            $table->string('image_path')->nullable();
            $table->string('image_original_name')->nullable();
            $table->string('image_mime_type')->nullable();
            $table->unsignedBigInteger('image_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_prompts');
    }
};
