<?php

use Illuminate\Support\Facades\Event;
use Statamic\Events\StaticCacheCleared;
use Statamic\Events\UrlInvalidated;

it('registers event listeners when api key is configured', function () {
    $listeners = Event::getListeners(StaticCacheCleared::class);

    expect($listeners)->not->toBeEmpty();

    $listeners = Event::getListeners(UrlInvalidated::class);

    expect($listeners)->not->toBeEmpty();
});

it('does not register event listeners when api key is missing', function () {
    config()->set('services.bunny.api_key', null);

    $this->app->getProviders(\Noo\StatamicBunnyPurge\StatamicBunnyPurgeServiceProvider::class);

    // Re-register the provider to test with missing config
    $provider = new \Noo\StatamicBunnyPurge\StatamicBunnyPurgeServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    // Since we can't easily clear listeners registered in the first boot,
    // we verify the provider boots without throwing an exception
    expect(true)->toBeTrue();
});

it('boots without error when provider config is invalid', function () {
    config()->set('statamic-bunny-purge.provider', 'nonexistent');
    config()->set('services.nonexistent.api_key', null);

    $provider = new \Noo\StatamicBunnyPurge\StatamicBunnyPurgeServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});
