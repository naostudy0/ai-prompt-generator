<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class SaveIllustrationPromptTagGroupsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'groups' => ['required', 'array', 'min:1', 'max:10'],
            'groups.*' => ['required', 'array:label,tags'],
            'groups.*.label' => ['required', 'string', 'max:100', 'regex:/\S/u'],
            'groups.*.tags' => ['required', 'string', 'regex:/[^,\s]/u'],
        ];
    }
}
