<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class SaveNamedPromptRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'regex:/\S/u'],
            'content' => ['required', 'string', 'regex:/[^,\s]/u'],
        ];
    }
}
