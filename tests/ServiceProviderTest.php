<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Noo\StatamicBunnyPurge\Jobs\PurgeAllJob;
use Noo\StatamicBunnyPurge\PurgingInvalidator;
use Noo\StatamicBunnyPurge\StatamicBunnyPurgeServiceProvider;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetSaved;
use Statamic\Events\StaticCacheCleared;
use Statamic\Events\UrlInvalidated;
use Statamic\StaticCaching\Invalidator;

it('registers event listeners when api key is configured', function () {
    $listeners = Event::getListeners(StaticCacheCleared::class);

    expect($listeners)->not->toBeEmpty();

    $listeners = Event::getListeners(UrlInvalidated::class);

    expect($listeners)->not->toBeEmpty();

    $listeners = Event::getListeners(AssetReuploaded::class);

    expect($listeners)->not->toBeEmpty();
});

it('does not purge the whole site on static cache clear when the toggle is disabled', function () {
    Event::forget(StaticCacheCleared::class);

    config()->set('statamic.bunny-purge.purge_all_on_static_cache_cleared', false);

    $provider = new StatamicBunnyPurgeServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    Queue::fake();

    event(new StaticCacheCleared);

    Queue::assertNotPushed(PurgeAllJob::class);

    // The per-URL listeners are unaffected by the toggle.
    expect(Event::getListeners(UrlInvalidated::class))->not->toBeEmpty();
});

it('does not register event listeners when api key is missing', function () {
    $events = [StaticCacheCleared::class, UrlInvalidated::class, AssetReuploaded::class, AssetSaved::class, AssetDeleted::class];

    foreach ($events as $event) {
        Event::forget($event);
    }

    $this->app->forgetExtenders(Invalidator::class);

    config()->set('statamic.bunny-purge.api_key', null);

    $provider = new StatamicBunnyPurgeServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    foreach ($events as $event) {
        expect(app('events')->getRawListeners()[$event] ?? [])->toBeEmpty();
    }

    expect(app(Invalidator::class))->not->toBeInstanceOf(PurgingInvalidator::class);
});
