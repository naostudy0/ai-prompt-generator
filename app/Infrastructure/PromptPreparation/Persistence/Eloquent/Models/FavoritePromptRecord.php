<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class FavoritePromptRecord extends Model
{
    protected $table = 'favorite_prompts';

    /** @var list<string> */
    protected $fillable = [
        'name', 'positive_prompt', 'negative_prompt', 'selection_snapshot', 'selection_summary',
        'image_path', 'image_original_name', 'image_mime_type', 'image_size',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['selection_snapshot' => 'array', 'selection_summary' => 'array'];
    }
}
