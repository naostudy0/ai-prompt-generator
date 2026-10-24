<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeCharacterLoraVisibilityRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['isHidden' => ['required', 'boolean']];
    }
}
