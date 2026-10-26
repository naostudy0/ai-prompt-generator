<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveRandomIllustrationIdeaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(['ideaId' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:2000']], GenerateRandomIllustrationIdeaRequest::selectionRules('fixedSelections.'));
    }
}
