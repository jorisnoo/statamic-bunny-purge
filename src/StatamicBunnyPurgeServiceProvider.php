<?php

namespace Noo\StatamicBunnyPurge;

use Illuminate\Support\Facades\Event;
use Noo\StatamicBunnyPurge\Listeners\PurgeAllOnStaticCacheCleared;
use Noo\StatamicBunnyPurge\Listeners\PurgeUrlOnUrlInvalidated;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Statamic\Events\StaticCacheCleared;
use Statamic\Events\UrlInvalidated;

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
            ], 'statamic-bunny-purge');
        }

        $this->app->singleton(CdnPurgeService::class);

        if (! filled(config('statamic.bunny-purge.api_key'))) {
            return;
        }

        Event::listen(StaticCacheCleared::class, PurgeAllOnStaticCacheCleared::class);
        Event::listen(UrlInvalidated::class, PurgeUrlOnUrlInvalidated::class);
    }
}
