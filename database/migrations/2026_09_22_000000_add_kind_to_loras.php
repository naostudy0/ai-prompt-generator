<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE loras
            ADD COLUMN kind TEXT NOT NULL DEFAULT 'character'
                CHECK (kind IN ('character', 'clothing'))
            SQL);
        Schema::table('loras', function (Blueprint $table): void {
            $table->index('kind');
        });
    }

    public function down(): void
    {
        if (DB::table('loras')->where('kind', 'clothing')->exists()) {
            throw new \RuntimeException('Clothing LoRA data must be removed before rolling back its kind.');
        }
        Schema::table('loras', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
