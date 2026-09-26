<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class QueueComfyUiPromptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'positive' => $this->input('positive', ''),
            'negative' => $this->input('negative', ''),
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'modelFamilyId' => ['required', 'integer', 'exists:model_families,id'],
            'positive' => ['present', 'nullable', 'string', 'max:1000000'],
            'negative' => ['present', 'nullable', 'string', 'max:1000000'],
        ];
    }
}
