<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $content
 */
class ActionPromptRecord extends Model
{
    protected $table = 'action_prompts';

    /** @var list<string> */
    protected $fillable = ['name', 'content'];
}
