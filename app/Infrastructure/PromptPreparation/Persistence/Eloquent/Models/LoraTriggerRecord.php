<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoraTriggerRecord extends Model
{
    protected $table = 'lora_triggers';

    /** @var list<string> */
    protected $fillable = ['lora_id', 'name', 'content'];

    /** @return BelongsTo<LoraRecord, $this> */
    public function lora(): BelongsTo
    {
        return $this->belongsTo(LoraRecord::class, 'lora_id');
    }
}
