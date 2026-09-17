<?php

use App\Http\Controllers\PromptPreparation\PromptPreparationPageController;
use App\Http\Controllers\PromptPreparation\Queries\GetDefaultPromptsController;
use App\Http\Controllers\PromptPreparation\Queries\GetLoraPromptOptionsController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteLoraController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteLoraTriggerController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteOutfitPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveDefaultPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveLoraController;
use App\Http\Controllers\PromptPreparation\Writes\SaveLoraTriggerController;
use App\Http\Controllers\PromptPreparation\Writes\SaveOutfitPromptController;
use Illuminate\Support\Facades\Route;

Route::get('/', PromptPreparationPageController::class)->name('prompt-preparation');

Route::get('/default-prompts', GetDefaultPromptsController::class)
    ->name('default-prompts.index');
Route::put('/default-prompts/{polarity}', SaveDefaultPromptController::class)
    ->whereIn('polarity', ['positive', 'negative'])
    ->name('default-prompts.update');

Route::get('/lora-prompt-options', GetLoraPromptOptionsController::class)
    ->name('lora-prompt-options.index');

Route::post('/loras', SaveLoraController::class)->name('loras.store');
Route::put('/loras/{lora}', SaveLoraController::class)->whereNumber('lora')->name('loras.update');
Route::delete('/loras/{lora}', DeleteLoraController::class)->whereNumber('lora')->name('loras.destroy');

Route::post('/lora-triggers', SaveLoraTriggerController::class)->name('lora-triggers.store');
Route::put('/lora-triggers/{trigger}', SaveLoraTriggerController::class)
    ->whereNumber('trigger')->name('lora-triggers.update');
Route::delete('/lora-triggers/{trigger}', DeleteLoraTriggerController::class)
    ->whereNumber('trigger')->name('lora-triggers.destroy');

Route::post('/outfits', SaveOutfitPromptController::class)->name('outfits.store');
Route::put('/outfits/{outfit}', SaveOutfitPromptController::class)
    ->whereNumber('outfit')->name('outfits.update');
Route::delete('/outfits/{outfit}', DeleteOutfitPromptController::class)
    ->whereNumber('outfit')->name('outfits.destroy');
