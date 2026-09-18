<?php

namespace App\Providers;

use App\Application\Shared\TransactionManager;
use App\Application\PromptPreparation\Ports\CharacterDirectionQueryService;
use App\Application\PromptPreparation\Ports\SceneDirectionQueryService;
use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Application\PromptPreparation\Ports\LoraPromptOptionQueryService;
use App\Application\PromptPreparation\Ports\OptionPromptQueryService;
use App\Domain\PromptPreparation\Repositories\ActionPromptRepository;
use App\Domain\PromptPreparation\Repositories\CompositionPromptRepository;
use App\Domain\PromptPreparation\Repositories\ExpressionPromptRepository;
use App\Domain\PromptPreparation\Repositories\GazePromptRepository;
use App\Domain\PromptPreparation\Repositories\LocationPromptRepository;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use App\Domain\PromptPreparation\Repositories\LoraRepository;
use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;
use App\Domain\PromptPreparation\Repositories\OutfitPromptRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentActionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentCompositionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentExpressionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentGazePromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentLocationPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentDefaultPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentLoraRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentLoraTriggerRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentOutfitPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentOptionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentOptionPromptGroupRepository;
use App\Infrastructure\PromptPreparation\Queries\EloquentCharacterDirectionQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentDefaultPromptQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentSceneDirectionQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentLoraPromptOptionQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentOptionPromptQueryService;
use Illuminate\Support\ServiceProvider;
use App\Infrastructure\Shared\LaravelTransactionManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CharacterDirectionQueryService::class, EloquentCharacterDirectionQueryService::class);
        $this->app->bind(SceneDirectionQueryService::class, EloquentSceneDirectionQueryService::class);
        $this->app->bind(ActionPromptRepository::class, EloquentActionPromptRepository::class);
        $this->app->bind(CompositionPromptRepository::class, EloquentCompositionPromptRepository::class);
        $this->app->bind(ExpressionPromptRepository::class, EloquentExpressionPromptRepository::class);
        $this->app->bind(GazePromptRepository::class, EloquentGazePromptRepository::class);
        $this->app->bind(LocationPromptRepository::class, EloquentLocationPromptRepository::class);
        $this->app->bind(DefaultPromptRepository::class, EloquentDefaultPromptRepository::class);
        $this->app->bind(DefaultPromptQueryService::class, EloquentDefaultPromptQueryService::class);
        $this->app->bind(LoraRepository::class, EloquentLoraRepository::class);
        $this->app->bind(LoraTriggerRepository::class, EloquentLoraTriggerRepository::class);
        $this->app->bind(OutfitPromptRepository::class, EloquentOutfitPromptRepository::class);
        $this->app->bind(LoraPromptOptionQueryService::class, EloquentLoraPromptOptionQueryService::class);
        $this->app->bind(OptionPromptRepository::class, EloquentOptionPromptRepository::class);
        $this->app->bind(OptionPromptGroupRepository::class, EloquentOptionPromptGroupRepository::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(OptionPromptQueryService::class, EloquentOptionPromptQueryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
