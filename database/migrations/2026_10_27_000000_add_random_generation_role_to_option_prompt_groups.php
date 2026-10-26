<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('option_prompt_groups', function (Blueprint $table): void {
            $table->string('random_generation_role')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('option_prompt_groups', function (Blueprint $table): void {
            $table->dropColumn('random_generation_role');
        });
    }
};
