<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('option_prompt_groups', function (Blueprint $table): void {
            $table->string('system_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('option_prompt_groups', function (Blueprint $table): void {
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });
    }
};
