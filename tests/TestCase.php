<?php

namespace Noo\StatamicBunnyPurge\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noo\StatamicBunnyPurge\StatamicBunnyPurgeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Noo\\StatamicBunnyPurge\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            StatamicBunnyPurgeServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('statamic-bunny-purge.provider', 'bunny');
        config()->set('statamic-bunny-purge.site_url', 'https://example.com');
        config()->set('services.bunny.api_key', 'test-key');
    }
}
