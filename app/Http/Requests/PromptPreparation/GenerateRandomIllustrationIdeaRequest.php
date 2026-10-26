<?php

namespace App\Http\Requests\PromptPreparation;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateRandomIllustrationIdeaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::selectionRules('fixedSelections.');
    }

    /** @return array<string,mixed> */
    public static function selectionRules(string $prefix): array
    {
        return [
            rtrim($prefix, '.') => ['required', 'array:positiveDefault,negativeDefault,modelFamilyId,character,clothingLoras,optionIds'],
            $prefix.'positiveDefault' => ['required', 'boolean'], $prefix.'negativeDefault' => ['required', 'boolean'],
            $prefix.'modelFamilyId' => ['required', 'integer', 'min:1'],
            $prefix.'character' => ['required', 'array:loraId,strength,triggerId,outfitId'],
            $prefix.'character.loraId' => ['nullable', 'integer', 'min:1'], $prefix.'character.strength' => ['required', 'numeric', 'min:0', 'max:1', 'multiple_of:0.1'],
            $prefix.'character.triggerId' => ['nullable', 'integer', 'min:1'], $prefix.'character.outfitId' => ['nullable', 'integer', 'min:1'],
            $prefix.'clothingLoras' => ['present', 'array'], $prefix.'clothingLoras.*' => ['array:loraId,strength,triggerId'],
            $prefix.'clothingLoras.*.loraId' => ['required', 'integer', 'min:1', 'distinct'], $prefix.'clothingLoras.*.strength' => ['required', 'numeric', 'min:0', 'max:1', 'multiple_of:0.1'],
            $prefix.'clothingLoras.*.triggerId' => ['nullable', 'integer', 'min:1'],
            $prefix.'optionIds' => ['present', 'array'], $prefix.'optionIds.*' => ['integer', 'min:1', 'distinct'],
        ];
    }
}
