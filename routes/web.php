<?php

use App\Http\Controllers\PromptPreparation\Queries\GetDefaultPromptsController;
use App\Http\Controllers\PromptPreparation\Writes\SaveDefaultPromptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/default-prompts', GetDefaultPromptsController::class)
    ->name('default-prompts.index');
Route::put('/default-prompts/{polarity}', SaveDefaultPromptController::class)
    ->whereIn('polarity', ['positive', 'negative'])
    ->name('default-prompts.update');
