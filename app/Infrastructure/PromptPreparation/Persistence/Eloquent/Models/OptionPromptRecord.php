<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $content
 * @property int $option_prompt_group_id
 */
class OptionPromptRecord extends Model
{
    protected $table = 'option_prompts';

    /** @var list<string> */
    protected $fillable = ['option_prompt_group_id', 'name', 'content'];
}
