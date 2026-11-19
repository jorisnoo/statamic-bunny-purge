<?php

namespace Noo\StatamicBunnyPurge\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Noo\StatamicBunnyPurge\StatamicBunnyPurgeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Statamic\StaticCaching\ServiceProvider as StaticCachingServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();

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
        config()->set('statamic.bunny-purge.api_key', 'test-key');

        // Statamic's own bindings for the static cacher and the Invalidator
        // this addon decorates, without booting the rest of Statamic.
        (new StaticCachingServiceProvider($app))->register();
    }
}
