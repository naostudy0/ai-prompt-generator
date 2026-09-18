<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** @var list<array{name: string, mode: string, table: string}> */
    private array $categories = [
        ['name' => '表情', 'mode' => 'multiple', 'table' => 'expression_prompts'],
        ['name' => '視線', 'mode' => 'single', 'table' => 'gaze_prompts'],
        ['name' => '動作', 'mode' => 'multiple', 'table' => 'action_prompts'],
        ['name' => '場所', 'mode' => 'multiple', 'table' => 'location_prompts'],
        ['name' => '構図', 'mode' => 'single', 'table' => 'composition_prompts'],
    ];

    public function up(): void
    {
        if (DB::table('outfits')->whereNull('lora_id')->exists()) {
            throw new \RuntimeException('LoRAに属していない服装があるため移行できません。');
        }

        Schema::create('option_prompt_groups_next', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->string('selection_mode');
            $table->unsignedInteger('position')->unique();
            $table->timestamps();
        });
        Schema::create('option_prompts_next', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('option_prompt_group_id');
            $table->text('name');
            $table->text('content');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['option_prompt_group_id', 'position']);
        });

        $position = 1;
        foreach ($this->categories as $category) {
            $groupId = $this->insertGroup($category['name'], $category['mode'], $position++);
            $this->copyItems($category['table'], $groupId);
        }

        $oldGroups = DB::table('option_prompt_groups')->orderBy('position')->get();
        foreach ($oldGroups as $group) {
            $groupId = $this->insertGroup('オプション'.(int) $group->position, 'multiple', $position++);
            $this->copyItems('option_prompts', $groupId, (int) $group->id);
        }

        Schema::drop('option_prompts');
        Schema::drop('option_prompt_groups');
        Schema::rename('option_prompt_groups_next', 'option_prompt_groups');
        Schema::rename('option_prompts_next', 'option_prompts');
        Schema::table('option_prompts', function (Blueprint $table): void {
            $table->foreign('option_prompt_group_id')->references('id')->on('option_prompt_groups')->restrictOnDelete();
        });

        foreach ($this->categories as $category) {
            Schema::drop($category['table']);
        }

        Schema::rename('outfits', 'outfits_nullable');
        Schema::create('outfits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lora_id')->constrained('loras')->cascadeOnDelete();
            $table->text('name');
            $table->text('content');
            $table->timestamps();
        });
        DB::table('outfits')->insertUsing(
            ['id', 'lora_id', 'name', 'content', 'created_at', 'updated_at'],
            DB::table('outfits_nullable')->select(['id', 'lora_id', 'name', 'content', 'created_at', 'updated_at']),
        );
        Schema::drop('outfits_nullable');
    }

    public function down(): void
    {
        foreach ($this->categories as $index => $category) {
            $group = DB::table('option_prompt_groups')->where('position', $index + 1)->first();
            if ($group === null || $group->name !== $category['name'] || $group->selection_mode !== $category['mode']) {
                throw new \RuntimeException('ブロックの編集または並べ替え後は安全に移行を戻せません。');
            }
        }

        foreach ($this->categories as $category) {
            Schema::create($category['table'], function (Blueprint $table): void {
                $table->id();
                $table->text('name');
                $table->text('content');
                $table->timestamps();
            });
            $groupId = DB::table('option_prompt_groups')->where('name', $category['name'])->value('id');
            if ($groupId !== null) {
                DB::table($category['table'])->insertUsing(
                    ['name', 'content', 'created_at', 'updated_at'],
                    DB::table('option_prompts')->where('option_prompt_group_id', $groupId)
                        ->orderBy('position')->select(['name', 'content', 'created_at', 'updated_at']),
                );
            }
        }

        Schema::rename('option_prompts', 'option_prompts_unified');
        Schema::rename('option_prompt_groups', 'option_prompt_groups_unified');
        Schema::create('option_prompt_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('position')->unique();
            $table->timestamps();
        });
        Schema::create('option_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('option_prompt_group_id')->constrained()->restrictOnDelete();
            $table->text('name');
            $table->text('content');
            $table->timestamps();
        });
        $legacyGroups = DB::table('option_prompt_groups_unified')->where('position', '>', count($this->categories))
            ->orderBy('position')->get();
        foreach ($legacyGroups as $index => $group) {
            $groupId = DB::table('option_prompt_groups')->insertGetId([
                'position' => $index + 1,
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ]);
            $legacyItems = DB::table('option_prompts_unified')
                ->where('option_prompt_group_id', $group->id)->orderBy('position')->get();
            foreach ($legacyItems as $item) {
                DB::table('option_prompts')->insert([
                    'option_prompt_group_id' => $groupId,
                    'name' => $item->name,
                    'content' => $item->content,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        }
        Schema::drop('option_prompts_unified');
        Schema::drop('option_prompt_groups_unified');

        Schema::rename('outfits', 'outfits_required');
        Schema::create('outfits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lora_id')->nullable()->constrained('loras')->nullOnDelete();
            $table->text('name');
            $table->text('content');
            $table->timestamps();
        });
        DB::table('outfits')->insertUsing(
            ['id', 'lora_id', 'name', 'content', 'created_at', 'updated_at'],
            DB::table('outfits_required')->select(['id', 'lora_id', 'name', 'content', 'created_at', 'updated_at']),
        );
        Schema::drop('outfits_required');
    }

    private function insertGroup(string $name, string $mode, int $position): int
    {
        return DB::table('option_prompt_groups_next')->insertGetId([
            'name' => $name,
            'selection_mode' => $mode,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copyItems(string $table, int $groupId, ?int $sourceGroupId = null): void
    {
        $query = DB::table($table)->orderBy('name')->orderBy('id');
        if ($sourceGroupId !== null) {
            $query->where('option_prompt_group_id', $sourceGroupId);
        }
        $sourceItems = $query->get();
        foreach ($sourceItems as $index => $item) {
            DB::table('option_prompts_next')->insert([
                'option_prompt_group_id' => $groupId,
                'name' => (string) $item->name,
                'content' => (string) $item->content,
                'position' => $index + 1,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);
        }
        $copiedItems = DB::table('option_prompts_next')->where('option_prompt_group_id', $groupId)
            ->orderBy('position')->get(['name', 'content', 'created_at', 'updated_at']);
        $sourceValues = $sourceItems->map(fn (object $item): array => [
            (string) $item->name,
            (string) $item->content,
            (string) $item->created_at,
            (string) $item->updated_at,
        ])->all();
        $copiedValues = $copiedItems->map(fn (object $item): array => [
            (string) $item->name,
            (string) $item->content,
            (string) $item->created_at,
            (string) $item->updated_at,
        ])->all();
        if ($sourceValues !== $copiedValues) {
            throw new \RuntimeException("{$table}の移行内容を検証できませんでした。");
        }
    }
};
