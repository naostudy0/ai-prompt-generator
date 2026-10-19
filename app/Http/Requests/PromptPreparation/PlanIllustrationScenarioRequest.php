<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class PlanIllustrationScenarioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $this->input('description'))]);
        }
    }
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['description' => ['required', 'string', 'min:1', 'max:2000'], 'sceneCount' => ['required', 'integer', 'between:3,10']];
    }
}
