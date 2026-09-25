<?php

use App\Http\Controllers\PromptPreparation\PromptPreparationPageController;
use App\Http\Controllers\PromptPreparation\Queries\GetDefaultPromptsController;
use App\Http\Controllers\PromptPreparation\Queries\GetClothingLoraOptionsController;
use App\Http\Controllers\PromptPreparation\Queries\GetLoraPromptOptionsController;
use App\Http\Controllers\PromptPreparation\Queries\GetPromptOptionsController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteLoraController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteClothingLoraController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteClothingLoraTriggerController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteLoraTriggerController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteOutfitPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveDefaultPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveClothingLoraController;
use App\Http\Controllers\PromptPreparation\Writes\SaveClothingLoraTriggerController;
use App\Http\Controllers\PromptPreparation\Writes\SaveLoraController;
use App\Http\Controllers\PromptPreparation\Writes\SaveLoraTriggerController;
use App\Http\Controllers\PromptPreparation\Writes\SaveOutfitPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveOptionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteOptionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\MoveOptionPromptController;
use App\Http\Controllers\PromptPreparation\Writes\MoveOptionPromptGroupController;
use App\Http\Controllers\PromptPreparation\Writes\SaveOptionPromptGroupController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PromptPreparation\Queries\ListFavoritePromptsController;
use App\Http\Controllers\PromptPreparation\Queries\GetFavoritePromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveFavoritePromptController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteFavoritePromptController;
use App\Http\Controllers\PromptPreparation\Queries\ListModelFamiliesController;
use App\Http\Controllers\PromptPreparation\Queries\GetFamilyDefaultPromptsController;
use App\Http\Controllers\PromptPreparation\Writes\SaveFamilyDefaultPromptController;
use App\Http\Controllers\PromptPreparation\Writes\SaveModelFamilyController;
use App\Http\Controllers\PromptPreparation\Writes\DeleteModelFamilyController;

Route::get('/', PromptPreparationPageController::class)->name('prompt-preparation');

Route::get('/default-prompts', GetDefaultPromptsController::class)
    ->name('default-prompts.index');
Route::put('/default-prompts/{polarity}', SaveDefaultPromptController::class)
    ->whereIn('polarity', ['positive', 'negative'])
    ->name('default-prompts.update');

Route::get('/model-families', ListModelFamiliesController::class)->name('model-families.index');
Route::post('/model-families', SaveModelFamilyController::class)->name('model-families.store');
Route::put('/model-families/{family}', SaveModelFamilyController::class)
    ->whereNumber('family')->name('model-families.update');
Route::delete('/model-families/{family}', DeleteModelFamilyController::class)
    ->whereNumber('family')->name('model-families.destroy');
Route::get('/model-families/{family}/default-prompts', GetFamilyDefaultPromptsController::class)
    ->whereNumber('family')->name('model-families.default-prompts.index');
Route::put('/model-families/{family}/default-prompts/{polarity}', SaveFamilyDefaultPromptController::class)
    ->whereNumber('family')->whereIn('polarity', ['positive', 'negative'])
    ->name('model-families.default-prompts.update');

Route::get('/lora-prompt-options', GetLoraPromptOptionsController::class)
    ->name('lora-prompt-options.index');
Route::get('/clothing-lora-options', GetClothingLoraOptionsController::class)
    ->name('clothing-lora-options.index');

Route::post('/clothing-loras', SaveClothingLoraController::class)
    ->name('clothing-loras.store');
Route::put('/clothing-loras/{clothingLora}', SaveClothingLoraController::class)
    ->whereNumber('clothingLora')->name('clothing-loras.update');
Route::delete('/clothing-loras/{clothingLora}', DeleteClothingLoraController::class)
    ->whereNumber('clothingLora')->name('clothing-loras.destroy');
Route::post('/clothing-lora-triggers', SaveClothingLoraTriggerController::class)
    ->name('clothing-lora-triggers.store');
Route::put(
    '/clothing-lora-triggers/{clothingLoraTrigger}',
    SaveClothingLoraTriggerController::class,
)->whereNumber('clothingLoraTrigger')->name('clothing-lora-triggers.update');
Route::delete(
    '/clothing-lora-triggers/{clothingLoraTrigger}',
    DeleteClothingLoraTriggerController::class,
)->whereNumber('clothingLoraTrigger')->name('clothing-lora-triggers.destroy');

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


Route::get('/prompt-options', GetPromptOptionsController::class)->name('prompt-options.index');
Route::post('/prompt-option-groups', SaveOptionPromptGroupController::class)->name('prompt-option-groups.store');
Route::put('/prompt-option-groups/{group}', SaveOptionPromptGroupController::class)
    ->whereNumber('group')->name('prompt-option-groups.update');
Route::patch('/prompt-option-groups/{group}/position', MoveOptionPromptGroupController::class)
    ->whereNumber('group')->name('prompt-option-groups.move');
Route::post('/prompt-options', SaveOptionPromptController::class)->name('prompt-options.store');
Route::put('/prompt-options/{option}', SaveOptionPromptController::class)
    ->whereNumber('option')->name('prompt-options.update');
Route::delete('/prompt-options/{option}', DeleteOptionPromptController::class)
    ->whereNumber('option')->name('prompt-options.destroy');
Route::patch('/prompt-options/{option}/position', MoveOptionPromptController::class)
    ->whereNumber('option')->name('prompt-options.move');

Route::get('/favorite-prompts', ListFavoritePromptsController::class)->name('favorite-prompts.index');
Route::get('/favorite-prompts/{favoritePrompt}', GetFavoritePromptController::class)
    ->whereNumber('favoritePrompt')->name('favorite-prompts.show');
Route::post('/favorite-prompts', SaveFavoritePromptController::class)->name('favorite-prompts.store');
Route::put('/favorite-prompts/{favoritePrompt}', SaveFavoritePromptController::class)
    ->whereNumber('favoritePrompt')->name('favorite-prompts.update');
Route::delete('/favorite-prompts/{favoritePrompt}', DeleteFavoritePromptController::class)
    ->whereNumber('favoritePrompt')->name('favorite-prompts.destroy');
