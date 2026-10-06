<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('option_categories', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->unsignedInteger('position')->unique();
            $table->timestamps();
        });

        Schema::table('option_prompt_groups', function (Blueprint $table): void {
            $table->foreignId('option_category_id')->nullable()
                ->after('id')->constrained('option_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('option_prompt_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('option_category_id');
        });
        Schema::dropIfExists('option_categories');
    }
};
