<?php

namespace App\Providers;

use App\Application\Shared\TransactionManager;
use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Application\PromptPreparation\Ports\ClothingLoraOptionQueryService;
use App\Application\PromptPreparation\Ports\LoraPromptOptionQueryService;
use App\Application\PromptPreparation\Ports\OptionPromptQueryService;
use App\Application\PromptPreparation\Ports\FavoriteImageStorage;
use App\Application\PromptPreparation\Ports\FavoritePromptQueryService;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use App\Domain\PromptPreparation\Repositories\LoraRepository;
use App\Domain\PromptPreparation\Repositories\LoraTriggerRepository;
use App\Domain\PromptPreparation\Repositories\OutfitPromptRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptRepository;
use App\Domain\PromptPreparation\Repositories\OptionPromptGroupRepository;
use App\Domain\PromptPreparation\Repositories\FavoritePromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentDefaultPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentLoraRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentLoraTriggerRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentOutfitPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentOptionPromptRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentOptionPromptGroupRepository;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentFavoritePromptRepository;
use App\Infrastructure\PromptPreparation\Queries\EloquentDefaultPromptQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentClothingLoraOptionQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentLoraPromptOptionQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentOptionPromptQueryService;
use App\Infrastructure\PromptPreparation\Queries\EloquentFavoritePromptQueryService;
use App\Infrastructure\PromptPreparation\Storage\LaravelFavoriteImageStorage;
use Illuminate\Support\ServiceProvider;
use App\Infrastructure\Shared\LaravelTransactionManager;
use App\Application\PromptPreparation\Ports\ModelFamilyQueryService;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;
use App\Infrastructure\PromptPreparation\Queries\EloquentModelFamilyQueryService;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentModelFamilyRepository;
use App\Application\PromptPreparation\Ports\ComfyUiWorkflowStore;
use App\Application\PromptPreparation\Ports\ImageGenerationQueue;
use App\Infrastructure\PromptPreparation\External\ComfyUi\ComfyUiImageGenerationQueue;
use App\Infrastructure\PromptPreparation\Storage\LaravelComfyUiWorkflowStore;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DefaultPromptRepository::class, EloquentDefaultPromptRepository::class);
        $this->app->bind(ModelFamilyRepository::class, EloquentModelFamilyRepository::class);
        $this->app->bind(ModelFamilyQueryService::class, EloquentModelFamilyQueryService::class);
        $this->app->bind(DefaultPromptQueryService::class, EloquentDefaultPromptQueryService::class);
        $this->app->bind(ClothingLoraOptionQueryService::class, EloquentClothingLoraOptionQueryService::class);
        $this->app->bind(LoraRepository::class, EloquentLoraRepository::class);
        $this->app->bind(LoraTriggerRepository::class, EloquentLoraTriggerRepository::class);
        $this->app->bind(OutfitPromptRepository::class, EloquentOutfitPromptRepository::class);
        $this->app->bind(LoraPromptOptionQueryService::class, EloquentLoraPromptOptionQueryService::class);
        $this->app->bind(OptionPromptRepository::class, EloquentOptionPromptRepository::class);
        $this->app->bind(OptionPromptGroupRepository::class, EloquentOptionPromptGroupRepository::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(OptionPromptQueryService::class, EloquentOptionPromptQueryService::class);
        $this->app->bind(FavoritePromptRepository::class, EloquentFavoritePromptRepository::class);
        $this->app->bind(FavoritePromptQueryService::class, EloquentFavoritePromptQueryService::class);
        $this->app->bind(FavoriteImageStorage::class, LaravelFavoriteImageStorage::class);
        $this->app->bind(ComfyUiWorkflowStore::class, LaravelComfyUiWorkflowStore::class);
        $this->app->bind(ImageGenerationQueue::class, ComfyUiImageGenerationQueue::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
