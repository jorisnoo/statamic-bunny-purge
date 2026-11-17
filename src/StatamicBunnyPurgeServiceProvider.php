<?php

namespace Noo\StatamicBunnyPurge;

use Illuminate\Support\Facades\Event;
use Noo\StatamicBunnyPurge\Listeners\PurgeAllOnStaticCacheCleared;
use Noo\StatamicBunnyPurge\Listeners\PurgeUrlOnAssetDeleted;
use Noo\StatamicBunnyPurge\Listeners\PurgeUrlOnAssetReuploaded;
use Noo\StatamicBunnyPurge\Listeners\PurgeUrlOnAssetSaved;
use Noo\StatamicBunnyPurge\Listeners\PurgeUrlOnUrlInvalidated;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetSaved;
use Statamic\Events\StaticCacheCleared;
use Statamic\Events\UrlInvalidated;
use Statamic\StaticCaching\Invalidator;

class StatamicBunnyPurgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('statamic-bunny-purge');
    }

    public function register(): static
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/bunny-purge.php', 'statamic.bunny-purge');

        return $this;
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/bunny-purge.php' => config_path('statamic/bunny-purge.php'),
            ], 'bunny-purge');
        }

        $this->app->singleton(CdnPurgeService::class);
        $this->app->singleton(UrlCollector::class);

        if (! filled(config('statamic.bunny-purge.api_key'))) {
            return;
        }

        if (config('statamic.bunny-purge.purge_all_on_static_cache_cleared', true)) {
            Event::listen(StaticCacheCleared::class, PurgeAllOnStaticCacheCleared::class);
        }

        // Hook Statamic's invalidator so the URLs of one change are purged in
        // batches, and so rules set to 'all' (which fire no event) still purge.
        $this->app->extend(Invalidator::class, fn (Invalidator $invalidator, $app) => $invalidator instanceof PurgingInvalidator
            ? $invalidator
            : new PurgingInvalidator($invalidator, $app->make(UrlCollector::class)));

        Event::listen(UrlInvalidated::class, PurgeUrlOnUrlInvalidated::class);
        Event::listen(AssetReuploaded::class, PurgeUrlOnAssetReuploaded::class);
        Event::listen(AssetSaved::class, PurgeUrlOnAssetSaved::class);
        Event::listen(AssetDeleted::class, PurgeUrlOnAssetDeleted::class);
    }
}
