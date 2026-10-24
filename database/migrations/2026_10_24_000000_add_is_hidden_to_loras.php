<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('loras', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('loras', function (Blueprint $table): void {
            $table->dropIndex(['is_hidden']);
            $table->dropColumn('is_hidden');
        });
    }
};
