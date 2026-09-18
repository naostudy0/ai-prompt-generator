<?php

use App\Http\Controllers\PromptPreparation\PromptPreparationPageController;
use App\Http\Controllers\PromptPreparation\Queries\GetCharacterDirectionsController;
use App\Http\Controllers\PromptPreparation\Queries\GetSceneDirectionsController;
use App\Http\Controllers\PromptPreparation\Writes\SaveExpressionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveGazePromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveLocationPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveCompositionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveActionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteExpressionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteGazePromptController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteLocationPromptController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteCompositionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteActionPromptController;
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


Route::get('/character-directions', GetCharacterDirectionsController::class)->name('character-directions.index');
Route::get('/scene-directions', GetSceneDirectionsController::class)->name('scene-directions.index');
Route::post('/expressions', SaveExpressionPromptController::class)->name('expressions.store');
Route::put('/expressions/{expression}', SaveExpressionPromptController::class)->whereNumber('expression')->name('expressions.update');
Route::delete('/expressions/{expression}', DeleteExpressionPromptController::class)->whereNumber('expression')->name('expressions.destroy');
Route::post('/gazes', SaveGazePromptController::class)->name('gazes.store');
Route::put('/gazes/{gaze}', SaveGazePromptController::class)->whereNumber('gaze')->name('gazes.update');
Route::delete('/gazes/{gaze}', DeleteGazePromptController::class)->whereNumber('gaze')->name('gazes.destroy');
Route::post('/locations', SaveLocationPromptController::class)->name('locations.store');
Route::put('/locations/{location}', SaveLocationPromptController::class)->whereNumber('location')->name('locations.update');
Route::delete('/locations/{location}', DeleteLocationPromptController::class)->whereNumber('location')->name('locations.destroy');
Route::post('/compositions', SaveCompositionPromptController::class)->name('compositions.store');
Route::put('/compositions/{composition}', SaveCompositionPromptController::class)->whereNumber('composition')->name('compositions.update');
Route::delete('/compositions/{composition}', DeleteCompositionPromptController::class)->whereNumber('composition')->name('compositions.destroy');
Route::post('/actions', SaveActionPromptController::class)->name('actions.store');
Route::put('/actions/{action}', SaveActionPromptController::class)->whereNumber('action')->name('actions.update');
Route::delete('/actions/{action}', DeleteActionPromptController::class)->whereNumber('action')->name('actions.destroy');
