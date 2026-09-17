<?php

namespace App\Providers;

use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Application\PromptPreparation\Ports\LoraPromptOptionQueryService;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use App\Domain\PromptPreparation\Repositories\LoraRepository;
use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;
use App\Domain\PromptPreparation\Repositories\OutfitPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentDefaultPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentLoraRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentLoraTriggerRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentOutfitPromptRepository;
use App\Infrastructure\PromptPreparation\Queries\EloquentDefaultPromptQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentLoraPromptOptionQueryService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DefaultPromptRepository::class, EloquentDefaultPromptRepository::class);
        $this->app->bind(DefaultPromptQueryService::class, EloquentDefaultPromptQueryService::class);
        $this->app->bind(LoraRepository::class, EloquentLoraRepository::class);
        $this->app->bind(LoraTriggerRepository::class, EloquentLoraTriggerRepository::class);
        $this->app->bind(OutfitPromptRepository::class, EloquentOutfitPromptRepository::class);
        $this->app->bind(LoraPromptOptionQueryService::class, EloquentLoraPromptOptionQueryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
