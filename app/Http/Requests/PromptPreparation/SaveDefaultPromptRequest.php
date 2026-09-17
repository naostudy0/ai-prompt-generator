<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class SaveDefaultPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['present', 'string'],
        ];
    }

    public function content(): string
    {
        /** @var string $content */
        $content = $this->validated('content');

        return $content;
    }
}
