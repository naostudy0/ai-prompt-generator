<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class AnalyzeComfyUiImageRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['image' => ['required', 'file', 'mimes:png', 'max:20480']];
    }
}
