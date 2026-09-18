<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $content
 */
class OptionPromptRecord extends Model
{
    protected $table = 'option_prompts';

    /** @var list<string> */
    protected $fillable = ['name', 'content'];
}
