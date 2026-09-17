<?php

namespace App\Providers;

use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentDefaultPromptRepository;
use App\Infrastructure\PromptPreparation\Queries\EloquentDefaultPromptQueryService;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
