<?php
namespace App\Http\Requests\PromptPreparation;

final class RegenerateScenarioScenePromptRequest extends GenerateScenarioPromptsRequest
{
    public function rules(): array
    {
        return parent::rules() + ['currentPrompts' => ['required', 'array', 'min:1'], 'currentPrompts.*.id' => ['required', 'string', 'max:100', 'distinct'], 'currentPrompts.*.positivePrompt' => ['required', 'string'], 'currentPrompts.*.negativePrompt' => ['present', 'nullable', 'string'], 'targetId' => ['required', 'string', 'max:100'], 'instruction' => ['present', 'nullable', 'string', 'max:1000']];
    }
}
