<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $content
 */
class CompositionPromptRecord extends Model
{
    protected $table = 'composition_prompts';

    /** @var list<string> */
    protected $fillable = ['name', 'content'];
}
