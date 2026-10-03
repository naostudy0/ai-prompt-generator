<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('generation_setting_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('model_family_id')->constrained('model_families')->cascadeOnDelete();
            $table->string('kind');
            $table->string('value');
            $table->timestamps();
            $table->unique(['model_family_id', 'kind', 'value']);
        });

        Schema::table('loras', function (Blueprint $table): void {
            $table->unsignedBigInteger('checkpoint_option_id')->nullable();
            $table->unsignedBigInteger('sampler_option_id')->nullable();
            $table->unsignedBigInteger('scheduler_option_id')->nullable();
        });

        $now = now();
        $defaults = [
            'checkpoint' => 'waiIllustriousSDXL_v90.safetensors',
            'sampler' => 'euler_ancestral',
            'scheduler' => 'normal',
        ];
        $familyIds = DB::table('loras')->where('kind', 'character')->distinct()->pluck('model_family_id');
        foreach ($familyIds as $familyId) {
            $optionIds = [];
            foreach ($defaults as $kind => $value) {
                $optionIds[$kind] = DB::table('generation_setting_options')->insertGetId([
                    'model_family_id' => $familyId,
                    'kind' => $kind,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('loras')->where('kind', 'character')->where('model_family_id', $familyId)->update([
                'checkpoint_option_id' => $optionIds['checkpoint'],
                'sampler_option_id' => $optionIds['sampler'],
                'scheduler_option_id' => $optionIds['scheduler'],
            ]);
        }

        DB::statement("CREATE TRIGGER loras_generation_settings_insert BEFORE INSERT ON loras
            WHEN (NEW.kind = 'character' AND (
                    NEW.checkpoint_option_id IS NULL OR NEW.sampler_option_id IS NULL OR NEW.scheduler_option_id IS NULL
                    OR NOT EXISTS (SELECT 1 FROM generation_setting_options WHERE id = NEW.checkpoint_option_id AND model_family_id = NEW.model_family_id AND kind = 'checkpoint')
                    OR NOT EXISTS (SELECT 1 FROM generation_setting_options WHERE id = NEW.sampler_option_id AND model_family_id = NEW.model_family_id AND kind = 'sampler')
                    OR NOT EXISTS (SELECT 1 FROM generation_setting_options WHERE id = NEW.scheduler_option_id AND model_family_id = NEW.model_family_id AND kind = 'scheduler')
                ))
              OR (NEW.kind = 'clothing' AND (NEW.checkpoint_option_id IS NOT NULL OR NEW.sampler_option_id IS NOT NULL OR NEW.scheduler_option_id IS NOT NULL))
            BEGIN SELECT RAISE(ABORT, 'LoRA kind and generation settings are inconsistent'); END");
        DB::statement("CREATE TRIGGER loras_generation_settings_update BEFORE UPDATE ON loras
            WHEN (NEW.kind = 'character' AND (
                    NEW.checkpoint_option_id IS NULL OR NEW.sampler_option_id IS NULL OR NEW.scheduler_option_id IS NULL
                    OR NOT EXISTS (SELECT 1 FROM generation_setting_options WHERE id = NEW.checkpoint_option_id AND model_family_id = NEW.model_family_id AND kind = 'checkpoint')
                    OR NOT EXISTS (SELECT 1 FROM generation_setting_options WHERE id = NEW.sampler_option_id AND model_family_id = NEW.model_family_id AND kind = 'sampler')
                    OR NOT EXISTS (SELECT 1 FROM generation_setting_options WHERE id = NEW.scheduler_option_id AND model_family_id = NEW.model_family_id AND kind = 'scheduler')
                ))
              OR (NEW.kind = 'clothing' AND (NEW.checkpoint_option_id IS NOT NULL OR NEW.sampler_option_id IS NOT NULL OR NEW.scheduler_option_id IS NOT NULL))
            BEGIN SELECT RAISE(ABORT, 'LoRA kind and generation settings are inconsistent'); END");
        DB::statement("CREATE TRIGGER generation_setting_options_delete BEFORE DELETE ON generation_setting_options
            WHEN EXISTS (SELECT 1 FROM loras WHERE checkpoint_option_id = OLD.id OR sampler_option_id = OLD.id OR scheduler_option_id = OLD.id)
            BEGIN SELECT RAISE(ABORT, 'Generation setting option is in use'); END");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER loras_generation_settings_insert');
        DB::statement('DROP TRIGGER loras_generation_settings_update');
        DB::statement('DROP TRIGGER generation_setting_options_delete');
        Schema::table('loras', function (Blueprint $table): void {
            $table->dropColumn(['checkpoint_option_id', 'sampler_option_id', 'scheduler_option_id']);
        });
        Schema::dropIfExists('generation_setting_options');
    }
};
