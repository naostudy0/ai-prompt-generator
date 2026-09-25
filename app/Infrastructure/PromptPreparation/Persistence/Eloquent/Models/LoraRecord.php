<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $file_name
 * @property 'character'|'clothing' $kind
 * @property int $recommended_strength_step
 * @property int|null $model_family_id
 */
class LoraRecord extends Model
{
    protected $table = 'loras';

    /** @var list<string> */
    protected $fillable = ['name', 'file_name', 'recommended_strength_step', 'kind', 'model_family_id'];
}
