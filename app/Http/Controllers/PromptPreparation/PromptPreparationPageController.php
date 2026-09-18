<?php

namespace App\Http\Controllers\PromptPreparation;

use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PromptPreparationPageController extends Controller
{
    public function __invoke(): View
    {
        return view('prompt-preparation', [
            'defaultPromptsUrl' => route('default-prompts.index'),
            'positiveUpdateUrl' => route('default-prompts.update', [
                'polarity' => PromptPolarity::Positive->value,
            ]),
            'negativeUpdateUrl' => route('default-prompts.update', [
                'polarity' => PromptPolarity::Negative->value,
            ]),
            'loraPromptOptionsUrl' => route('lora-prompt-options.index'),
            'lorasUrl' => route('loras.store'),
            'loraTriggersUrl' => route('lora-triggers.store'),
            'outfitsUrl' => route('outfits.store'),
            'promptOptionsUrl' => route('prompt-options.index'),
            'promptOptionGroupsUrl' => route('prompt-option-groups.store'),
        ]);
    }
}
