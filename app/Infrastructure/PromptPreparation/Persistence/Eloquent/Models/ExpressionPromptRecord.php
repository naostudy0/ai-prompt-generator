<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $content
 */
class ExpressionPromptRecord extends Model
{
    protected $table = 'expression_prompts';

    /** @var list<string> */
    protected $fillable = ['name', 'content'];
}
