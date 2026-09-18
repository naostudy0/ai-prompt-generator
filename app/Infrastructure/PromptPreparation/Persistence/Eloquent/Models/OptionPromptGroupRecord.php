<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $position */
class OptionPromptGroupRecord extends Model
{
    protected $table = 'option_prompt_groups';

    /** @var list<string> */
    protected $fillable = ['position'];
}
