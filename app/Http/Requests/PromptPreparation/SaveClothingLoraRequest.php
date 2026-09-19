<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveClothingLoraRequest extends FormRequest
{
    /** @return array<string, list<string|\Illuminate\Validation\Rules\Unique>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'regex:/\S/u'],
            'fileName' => [
                'required',
                'string',
                'not_regex:/[<>:,\r\n]/',
                Rule::unique('loras', 'file_name')->ignore($this->route('clothingLora')),
            ],
            'recommendedStrength' => ['required', 'numeric', 'min:0', 'max:1', 'multiple_of:0.1'],
        ];
    }
}
