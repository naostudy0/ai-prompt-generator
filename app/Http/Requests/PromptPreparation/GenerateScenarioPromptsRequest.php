<?php
namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScenarioPromptsRequest extends FormRequest
{
    public function rules(): array
    {
        return ['description' => ['required', 'string', 'min:1', 'max:2000'], 'continuityNotes' => ['present', 'nullable', 'string', 'max:2000'], 'promptStyle' => ['sometimes', 'in:danbooru_tags,natural_language'], 'scenes' => ['required', 'array', 'between:3,10'], 'scenes.*.id' => ['required', 'string', 'max:100', 'distinct'], 'scenes.*.title' => ['required', 'string', 'max:100'], 'scenes.*.description' => ['required', 'string', 'max:1000'], 'scenes.*.changesFromPrevious' => ['present', 'nullable', 'string', 'max:1000']];
    }
}
