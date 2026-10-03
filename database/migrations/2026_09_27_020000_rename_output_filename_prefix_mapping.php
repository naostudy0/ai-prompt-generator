<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('comfy_ui_workflow_mappings')
            ->where('role', 'outputDirectory')
            ->update(['role' => 'outputFilenamePrefix']);
    }

    public function down(): void
    {
        DB::table('comfy_ui_workflow_mappings')
            ->where('role', 'outputFilenamePrefix')
            ->update(['role' => 'outputDirectory']);
    }
};
