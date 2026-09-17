<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class LoraTriggerRecord extends Model
{
    protected $table = 'lora_triggers';

    /** @var list<string> */
    protected $fillable = ['lora_id', 'name', 'content'];
}
