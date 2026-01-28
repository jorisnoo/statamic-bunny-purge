<?php

namespace Noo\StatamicBunnyPurge;

use Noo\StatamicBunnyPurge\Commands\StatamicBunnyPurgeCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class StatamicBunnyPurgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('statamic-bunny-purge')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_statamic_bunny_purge_table')
            ->hasCommand(StatamicBunnyPurgeCommand::class);
    }
}
