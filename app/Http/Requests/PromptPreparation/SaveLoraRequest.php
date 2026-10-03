<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveLoraRequest extends FormRequest
{
    /** @return array<string, list<string|\Illuminate\Validation\Rules\Unique>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'regex:/\S/u',
                'not_regex:/[<>:"\/\\\\|?*\x00-\x1F]/u',
                'not_regex:/\.$/u',
                'not_in:.,..',
            ],
            'fileName' => [
                'required',
                'string',
                'not_regex:/[<>:,\r\n]/',
                Rule::unique('loras', 'file_name')->ignore($this->route('lora')),
            ],
            'recommendedStrength' => ['required', 'numeric', 'min:0', 'max:1', 'multiple_of:0.1'],
            'modelFamilyId' => ['required', 'integer', Rule::exists('model_families', 'id')],
            'checkpointOptionId' => ['required', 'integer', Rule::exists('generation_setting_options', 'id')],
            'samplerOptionId' => ['required', 'integer', Rule::exists('generation_setting_options', 'id')],
            'schedulerOptionId' => ['required', 'integer', Rule::exists('generation_setting_options', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.not_regex' => '登録名にファイル名として使用できない文字が含まれています。',
            'name.not_in' => '登録名に「.」または「..」は使用できません。',
        ];
    }
}
