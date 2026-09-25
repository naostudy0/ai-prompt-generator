<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class DefaultPromptRecord extends Model
{
    protected $table = 'default_prompts';

    /** @var list<string> */
    protected $fillable = [
        'polarity',
        'model_family_id',
        'content',
    ];
}
