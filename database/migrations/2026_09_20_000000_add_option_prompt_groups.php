<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('option_prompt_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('position')->unique();
            $table->timestamps();
        });
        $groupId = DB::table('option_prompt_groups')->insertGetId([
            'position' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::rename('option_prompts', 'option_prompts_before_groups');
        Schema::create('option_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('option_prompt_group_id')->constrained()->restrictOnDelete();
            $table->text('name');
            $table->text('content');
            $table->timestamps();
        });
        DB::table('option_prompts')->insertUsing(
            ['id', 'option_prompt_group_id', 'name', 'content', 'created_at', 'updated_at'],
            DB::table('option_prompts_before_groups')->select([
                'id', DB::raw($groupId), 'name', 'content', 'created_at', 'updated_at',
            ]),
        );
        Schema::drop('option_prompts_before_groups');
    }

    public function down(): void
    {
        Schema::rename('option_prompts', 'option_prompts_with_groups');
        Schema::create('option_prompts', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->text('content');
            $table->timestamps();
        });
        DB::table('option_prompts')->insertUsing(
            ['id', 'name', 'content', 'created_at', 'updated_at'],
            DB::table('option_prompts_with_groups')->select(['id', 'name', 'content', 'created_at', 'updated_at']),
        );
        Schema::drop('option_prompts_with_groups');
        Schema::dropIfExists('option_prompt_groups');
    }
};
