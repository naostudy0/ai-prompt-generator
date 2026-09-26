<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class SaveComfyUiWorkflowRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'workflow' => ['required', 'file', 'max:5120'],
            'positiveNodeId' => ['required', 'string', 'max:255'],
            'positiveInputName' => ['required', 'string', 'max:255'],
            'negativeNodeId' => ['required', 'string', 'max:255'],
            'negativeInputName' => ['required', 'string', 'max:255'],
            'seedNodeId' => ['required', 'string', 'max:255'],
            'seedInputName' => ['required', 'string', 'max:255'],
        ];
    }
}
