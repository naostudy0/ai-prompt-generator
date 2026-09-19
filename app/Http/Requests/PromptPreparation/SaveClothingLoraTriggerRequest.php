<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveClothingLoraTriggerRequest extends FormRequest
{
    /** @return array<string, list<string|\Illuminate\Validation\Rules\Exists>> */
    public function rules(): array
    {
        return [
            'loraId' => [
                'required',
                'integer',
                Rule::exists('loras', 'id')->where('kind', 'clothing'),
            ],
            'name' => ['required', 'string', 'regex:/\S/u'],
            'content' => ['required', 'string', 'regex:/[^,\s]/u'],
        ];
    }
}
