<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveGenerationSettingOptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('family') !== null) {
            $this->merge(['modelFamilyId' => (int) $this->route('family')]);
        }
    }

    /** @return array<string, list<string|\Illuminate\Validation\Rules\In>> */
    public function rules(): array
    {
        return [
            'modelFamilyId' => ['required', 'integer', Rule::exists('model_families', 'id')],
            'kind' => ['required', 'string', Rule::in(['checkpoint', 'sampler', 'scheduler'])],
            'value' => ['required', 'string', 'max:255'],
        ];
    }
}
