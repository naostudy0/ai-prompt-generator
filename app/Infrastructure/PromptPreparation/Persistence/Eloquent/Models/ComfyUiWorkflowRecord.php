<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class ComfyUiWorkflowRecord extends Model
{
    protected $table = 'comfy_ui_workflows';

    protected $primaryKey = 'model_family_id';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'model_family_id', 'original_file_name', 'storage_path',
        'positive_node_id', 'positive_input_name', 'negative_node_id',
        'negative_input_name', 'seed_node_id', 'seed_input_name',
    ];
}
