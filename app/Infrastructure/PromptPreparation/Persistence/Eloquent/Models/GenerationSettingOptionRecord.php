<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class GenerationSettingOptionRecord extends Model
{
    protected $table = 'generation_setting_options';

    /** @var list<string> */
    protected $fillable = ['model_family_id', 'kind', 'value'];
}
