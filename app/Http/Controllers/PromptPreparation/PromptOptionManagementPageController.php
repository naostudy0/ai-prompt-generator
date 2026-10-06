<?php

namespace App\Http\Controllers\PromptPreparation;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PromptOptionManagementPageController extends Controller
{
    public function __invoke(): View
    {
        return view('prompt-option-management', [
            'promptPreparationUrl' => route('prompt-preparation'),
            'promptOptionsUrl' => route('prompt-options.index'),
            'promptOptionGroupsUrl' => route('prompt-option-groups.store'),
            'promptOptionCategoriesUrl' => route('prompt-option-categories.index'),
        ]);
    }
}
