<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("CREATE TRIGGER loras_model_family_insert BEFORE INSERT ON loras
            WHEN (NEW.kind = 'character' AND NEW.model_family_id IS NULL)
              OR (NEW.kind = 'clothing' AND NEW.model_family_id IS NOT NULL)
            BEGIN SELECT RAISE(ABORT, 'LoRA kind and model family are inconsistent'); END");
        DB::statement("CREATE TRIGGER loras_model_family_update BEFORE UPDATE ON loras
            WHEN (NEW.kind = 'character' AND NEW.model_family_id IS NULL)
              OR (NEW.kind = 'clothing' AND NEW.model_family_id IS NOT NULL)
            BEGIN SELECT RAISE(ABORT, 'LoRA kind and model family are inconsistent'); END");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER loras_model_family_insert');
        DB::statement('DROP TRIGGER loras_model_family_update');
    }
};
