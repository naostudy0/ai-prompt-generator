<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GetLoraPromptOptionsRequest extends FormRequest
{
    /** @return array<string, list<string|\Illuminate\Validation\Rules\In>> */
    public function rules(): array
    {
        return ['visibility' => ['sometimes', 'string', Rule::in(['visible', 'hidden', 'all'])]];
    }
}
