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
            'characterDirectionsUrl' => route('character-directions.index'),
            'sceneDirectionsUrl' => route('scene-directions.index'),
            'expressionsUrl' => route('expressions.store'),
            'gazesUrl' => route('gazes.store'),
            'locationsUrl' => route('locations.store'),
            'compositionsUrl' => route('compositions.store'),
            'actionsUrl' => route('actions.store'),
            'promptOptionsUrl' => route('prompt-options.index'),
        ]);
    }
}
