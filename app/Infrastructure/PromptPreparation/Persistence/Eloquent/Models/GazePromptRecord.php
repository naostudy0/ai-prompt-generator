<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $content
 */
class GazePromptRecord extends Model
{
    protected $table = 'gaze_prompts';

    /** @var list<string> */
    protected $fillable = ['name', 'content'];
}
