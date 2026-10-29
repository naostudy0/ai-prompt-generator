<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $lora_id
 * @property string $name
 * @property string $content
 * @property string $negative_content
 */
class OutfitPromptRecord extends Model
{
    protected $table = 'outfits';

    /** @var list<string> */
    protected $fillable = ['lora_id', 'name', 'content', 'negative_content'];
}
