<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('model_families', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->timestamps();
        });

        DB::table('model_families')->insert([
            'id' => 1,
            'name' => 'Illustrious',
            'normalized_name' => 'illustrious',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('default_prompts_next', function (Blueprint $table): void {
            $table->foreignId('model_family_id')->default(1)->constrained('model_families')->restrictOnDelete();
            $table->string('polarity');
            $table->text('content');
            $table->timestamps();
            $table->primary(['model_family_id', 'polarity']);
        });
        DB::table('default_prompts_next')->insertUsing(
            ['model_family_id', 'polarity', 'content', 'created_at', 'updated_at'],
            DB::table('default_prompts')->selectRaw('1, polarity, content, created_at, updated_at'),
        );
        Schema::drop('default_prompts');
        Schema::rename('default_prompts_next', 'default_prompts');

        DB::statement('ALTER TABLE loras ADD COLUMN model_family_id INTEGER REFERENCES model_families(id) ON DELETE RESTRICT');
        DB::table('loras')->where('kind', 'character')->update(['model_family_id' => 1]);
    }

    public function down(): void
    {
        if (DB::table('model_families')->where('id', '!=', 1)->exists()) {
            throw new RuntimeException('Remove additional model families before rolling back.');
        }

        DB::statement('ALTER TABLE loras DROP COLUMN model_family_id');
        Schema::create('default_prompts_previous', function (Blueprint $table): void {
            $table->string('polarity')->primary();
            $table->text('content');
            $table->timestamps();
        });
        DB::table('default_prompts_previous')->insertUsing(
            ['polarity', 'content', 'created_at', 'updated_at'],
            DB::table('default_prompts')->where('model_family_id', 1)
                ->select('polarity', 'content', 'created_at', 'updated_at'),
        );
        Schema::drop('default_prompts');
        Schema::rename('default_prompts_previous', 'default_prompts');
        Schema::drop('model_families');
    }
};
