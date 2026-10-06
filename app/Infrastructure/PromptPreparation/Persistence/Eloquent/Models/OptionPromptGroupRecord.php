<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $selection_mode
 * @property int $position
 * @property int|null $option_category_id
 */
class OptionPromptGroupRecord extends Model
{
    protected $table = 'option_prompt_groups';

    /** @var list<string> */
    protected $fillable = ['option_category_id', 'name', 'selection_mode', 'position'];
}
