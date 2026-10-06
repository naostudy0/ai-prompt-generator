<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property int $position
 */
class OptionCategoryRecord extends Model
{
    protected $table = 'option_categories';

    /** @var list<string> */
    protected $fillable = ['name', 'position'];
}
