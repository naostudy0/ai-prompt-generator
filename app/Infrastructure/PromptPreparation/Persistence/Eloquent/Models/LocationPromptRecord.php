<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $content
 */
class LocationPromptRecord extends Model
{
    protected $table = 'location_prompts';

    /** @var list<string> */
    protected $fillable = ['name', 'content'];
}
