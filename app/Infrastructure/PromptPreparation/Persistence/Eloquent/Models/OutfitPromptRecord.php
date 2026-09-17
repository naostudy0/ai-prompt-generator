<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class OutfitPromptRecord extends Model
{
    protected $table = 'outfits';

    /** @var list<string> */
    protected $fillable = ['lora_id', 'name', 'content'];
}
