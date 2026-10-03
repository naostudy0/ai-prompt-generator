<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('comfy_ui_workflow_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('model_family_id')->constrained('comfy_ui_workflows', 'model_family_id')->cascadeOnDelete();
            $table->string('role');
            $table->string('node_id');
            $table->string('input_name');
            $table->unsignedInteger('position')->default(0);
            $table->unique(['model_family_id', 'role', 'node_id', 'input_name']);
        });

        foreach (DB::table('comfy_ui_workflows')->get() as $workflow) {
            foreach (['positive', 'negative', 'seed'] as $position => $role) {
                DB::table('comfy_ui_workflow_mappings')->insert([
                    'model_family_id' => $workflow->model_family_id,
                    'role' => $role,
                    'node_id' => $workflow->{$role.'_node_id'},
                    'input_name' => $workflow->{$role.'_input_name'},
                    'position' => $position,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comfy_ui_workflow_mappings');
    }
};
