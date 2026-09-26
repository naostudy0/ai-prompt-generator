<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class QueueComfyUiPromptBatchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        if (is_array($items)) {
            $this->merge(['items' => array_map(function (mixed $item): mixed {
                if (!is_array($item)) {
                    return $item;
                }
                $item['positive'] ??= '';
                $item['negative'] ??= '';

                return $item;
            }, $items)]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.candidateKey' => ['required', 'string', 'max:255', 'distinct'],
            'items.*.modelFamilyId' => ['required', 'integer', 'exists:model_families,id'],
            'items.*.positive' => ['present', 'nullable', 'string', 'max:1000000'],
            'items.*.negative' => ['present', 'nullable', 'string', 'max:1000000'],
        ];
    }
}
