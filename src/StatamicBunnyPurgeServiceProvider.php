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
        $package
            ->name('statamic-bunny-purge')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        $this->app->singleton(BunnyPurgeService::class);

        Event::listen(StaticCacheCleared::class, PurgeAllOnStaticCacheCleared::class);
        Event::listen(UrlInvalidated::class, PurgeUrlOnUrlInvalidated::class);
    }
}
