<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class ModelFamilyRecord extends Model
{
    protected $table = 'model_families';

    /** @var list<string> */
    protected $fillable = ['name', 'normalized_name'];
}
