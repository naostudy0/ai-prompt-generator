<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SaveFavoritePromptRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'positivePrompt' => ['nullable', 'string', 'required_without:negativePrompt'],
            'negativePrompt' => ['nullable', 'string', 'required_without:positivePrompt'],
            'selectionSnapshot' => ['required', 'json', 'regex:/^\s*\{/'],
            'selectionSummary' => ['required', 'json', 'regex:/^\s*\[/'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:20480'],
            'removeImage' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $snapshot = $this->decode('selectionSnapshot');
            $summary = $this->decode('selectionSummary');
            if (! $this->validSnapshot($snapshot)) {
                $validator->errors()->add('selectionSnapshot', '選択状態の形式が正しくありません。');
            }
            if (! $this->validSummary($summary)) {
                $validator->errors()->add('selectionSummary', '選択概要の形式が正しくありません。');
            }
        }];
    }

    /** @return array<string, mixed>|null */
    public function selectionSnapshot(): ?array
    {
        $value = $this->decode('selectionSnapshot');

        return is_array($value) ? $value : null;
    }

    /** @return list<array<string, mixed>>|null */
    public function selectionSummary(): ?array
    {
        $value = $this->decode('selectionSummary');

        return is_array($value) && array_is_list($value) ? $value : null;
    }

    private function decode(string $key): mixed
    {
        $value = $this->input($key);
        if (! is_string($value)) {
            return null;
        }

        return json_decode($value, true);
    }

    private function validSnapshot(mixed $value): bool
    {
        if (! is_array($value) || array_is_list($value)) {
            return false;
        }
        $defaults = $value['defaults'] ?? null;
        $lora = $value['lora'] ?? null;
        $clothing = $value['clothingLoras'] ?? null;
        $optionIds = $value['optionIds'] ?? null;
        if (! is_array($defaults) || ! is_bool($defaults['positive'] ?? null) || ! is_bool($defaults['negative'] ?? null)) {
            return false;
        }
        if (! is_array($lora) || ! $this->nullablePositiveId($lora['loraId'] ?? null)
            || ! $this->validStrength($lora['strength'] ?? null)
            || ! $this->nullablePositiveId($lora['triggerId'] ?? null)
            || ! $this->nullablePositiveId($lora['outfitId'] ?? null)) {
            return false;
        }
        if (! is_array($clothing) || ! array_is_list($clothing)) {
            return false;
        }
        $clothingLoraIds = [];
        foreach ($clothing as $selected) {
            if (! is_array($selected) || ! $this->positiveId($selected['loraId'] ?? null)
                || ! $this->validStrength($selected['strength'] ?? null)
                || ! $this->nullablePositiveId($selected['triggerId'] ?? null)) {
                return false;
            }
            $clothingLoraIds[] = $selected['loraId'];
        }
        if (count(array_unique($clothingLoraIds)) !== count($clothingLoraIds)) {
            return false;
        }
        if (! is_array($optionIds) || ! array_is_list($optionIds)
            || array_filter($optionIds, fn (mixed $id): bool => ! $this->positiveId($id)) !== []
            || count(array_unique($optionIds)) !== count($optionIds)) {
            return false;
        }

        return true;
    }

    private function validSummary(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }
        foreach ($value as $group) {
            if (! is_array($group) || ! is_string($group['key'] ?? null)
                || ! is_string($group['label'] ?? null) || ! is_array($group['items'] ?? null)
                || ! array_is_list($group['items'])) {
                return false;
            }
            foreach ($group['items'] as $item) {
                if (! is_array($item) || ! is_string($item['label'] ?? null)
                    || ! is_string($item['meta'] ?? null) || ! is_array($item['details'] ?? null)
                    || ! array_is_list($item['details'])
                    || array_filter($item['details'], fn (mixed $detail): bool => ! is_string($detail)) !== []) {
                    return false;
                }
            }
        }

        return true;
    }

    private function positiveId(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }

    private function nullablePositiveId(mixed $value): bool
    {
        return $value === null || $this->positiveId($value);
    }

    private function validStrength(mixed $value): bool
    {
        return (is_int($value) || is_float($value))
            && $value >= 0 && $value <= 1
            && abs(($value * 10) - round($value * 10)) < 0.000001;
    }
}
