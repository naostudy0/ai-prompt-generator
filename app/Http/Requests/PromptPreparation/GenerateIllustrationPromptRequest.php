<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateIllustrationPromptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $description = $this->input('description');
        if (is_string($description)) {
            $description = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $description) ?? $description;
            $this->merge(['description' => $description]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:1', 'max:2000'],
            'promptStyle' => ['sometimes', 'string', 'in:danbooru_tags,natural_language'],
        ];
    }
}
