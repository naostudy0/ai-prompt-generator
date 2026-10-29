<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $lora_id
 * @property string $name
 * @property string $content
 * @property string $negative_content
 */
class LoraTriggerRecord extends Model
{
    protected $table = 'lora_triggers';

    /** @var list<string> */
    protected $fillable = ['lora_id', 'name', 'content', 'negative_content'];

    /** @return BelongsTo<LoraRecord, $this> */
    public function lora(): BelongsTo
    {
        return $this->belongsTo(LoraRecord::class, 'lora_id');
    }
}
