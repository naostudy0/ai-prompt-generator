<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('comfy_ui_workflows', function (Blueprint $table): void {
            $table->foreignId('model_family_id')->primary()->constrained('model_families')->cascadeOnDelete();
            $table->string('original_file_name');
            $table->string('storage_path')->unique();
            $table->string('positive_node_id');
            $table->string('positive_input_name');
            $table->string('negative_node_id');
            $table->string('negative_input_name');
            $table->string('seed_node_id');
            $table->string('seed_input_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comfy_ui_workflows');
    }
};
