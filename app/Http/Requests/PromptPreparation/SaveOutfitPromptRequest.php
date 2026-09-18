<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class SaveOutfitPromptRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'loraId' => ['required', 'integer', 'exists:loras,id'],
            'name' => ['required', 'string', 'regex:/\S/u'],
            'content' => ['required', 'string', 'regex:/[^,\s]/u'],
        ];
    }
}
