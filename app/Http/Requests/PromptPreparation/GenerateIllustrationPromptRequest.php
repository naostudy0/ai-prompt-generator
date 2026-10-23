<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class GenerateIllustrationPromptRequest extends FormRequest
{
    private const TEMPLATE_FIELDS = [
        'character',
        'action',
        'background',
        'composition',
        'atmosphere',
        'constraints',
    ];

    protected function prepareForValidation(): void
    {
        $this->merge(['inputMode' => $this->input('inputMode', 'free')]);
        $description = $this->input('description');
        if (is_string($description)) {
            $this->merge(['description' => $this->trimWhitespace($description)]);
        }

        $template = $this->input('template');
        if (is_array($template)) {
            foreach (self::TEMPLATE_FIELDS as $field) {
                if (is_string($template[$field] ?? null)) {
                    $template[$field] = $this->trimWhitespace($template[$field]);
                }
            }
            $this->merge(['template' => $template]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'inputMode' => ['required', 'string', 'in:free,template'],
            'description' => ['required_if:inputMode,free', 'nullable', 'string', 'min:1', 'max:2000'],
            'promptStyle' => ['sometimes', 'string', 'in:danbooru_tags,natural_language'],
            'template' => ['required_if:inputMode,template', 'array'],
            'template.character' => ['nullable', 'string', 'max:2000'],
            'template.action' => ['nullable', 'string', 'max:2000'],
            'template.background' => ['nullable', 'string', 'max:2000'],
            'template.composition' => ['nullable', 'string', 'max:2000'],
            'template.atmosphere' => ['nullable', 'string', 'max:2000'],
            'template.constraints' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('inputMode') !== 'template' || $validator->errors()->has('template')) {
                return;
            }
            foreach (self::TEMPLATE_FIELDS as $field) {
                if (is_string($this->input("template.{$field}")) && $this->input("template.{$field}") !== '') {
                    return;
                }
            }
            $validator->errors()->add('template', 'テンプレートの少なくとも1項目を入力してください。');
        }];
    }

    private function trimWhitespace(string $value): string
    {
        return preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value) ?? $value;
    }
}
