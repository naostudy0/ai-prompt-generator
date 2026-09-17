<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class DefaultPromptRecord extends Model
{
    protected $table = 'default_prompts';

    protected $primaryKey = 'polarity';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'polarity',
        'content',
    ];
}
