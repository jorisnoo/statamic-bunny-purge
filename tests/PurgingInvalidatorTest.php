<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Noo\StatamicBunnyPurge\Jobs\PurgeAllJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;
use Noo\StatamicBunnyPurge\PurgingInvalidator;
use Noo\StatamicBunnyPurge\UrlCollector;
use Statamic\Contracts\Entries\Entry;
use Statamic\Events\UrlInvalidated;
use Statamic\StaticCaching\DefaultInvalidator;
use Statamic\StaticCaching\Invalidate;
use Statamic\StaticCaching\Invalidator;

function invalidatorFiring(array $paths): Invalidator
{
    return new class($paths) implements Invalidator
    {
        public function __construct(private array $paths) {}

        public function invalidate($item)
        {
            foreach ($this->paths as $path) {
                event(new UrlInvalidated($path, 'https://example.com'));
            }
        }

        public function refresh($item)
        {
            $this->invalidate($item);
        }
    };
}

it('decorates the invalidator Statamic resolves for its Invalidate subscriber', function () {
    $invalidator = app(Invalidator::class);

    expect($invalidator)->toBeInstanceOf(PurgingInvalidator::class)
        ->and($invalidator->inner())->toBeInstanceOf(DefaultInvalidator::class);

    $subscriber = app(Invalidate::class);
    $property = new ReflectionProperty($subscriber, 'invalidator');

    expect($property->getValue($subscriber))->toBeInstanceOf(PurgingInvalidator::class);
});

it('purges every site when the invalidation rules are all', function () {
    config()->set('statamic.static_caching.invalidation.rules', 'all');
    config()->set('queue.default', 'sync');
    Http::fake(['*' => Http::response('', 200)]);
    fakeSites(['default' => 'https://example.com/']);

    // DefaultInvalidator flushes the cacher directly here, which fires
    // neither UrlInvalidated nor StaticCacheCleared.
    app(Invalidator::class)->invalidate(Mockery::mock(Entry::class));

    Http::assertSent(fn (Request $request) => $request['urls'] === ['https://example.com']);
    Http::assertSent(fn (Request $request) => $request['urls'] === ['https://example.com/*']);
});

it('purges every site on refresh when the invalidation rules are all', function () {
    config()->set('statamic.static_caching.invalidation.rules', 'all');
    Queue::fake();

    app(Invalidator::class)->refresh(Mockery::mock(Entry::class));

    Queue::assertPushed(PurgeAllJob::class, 1);
});

it('does not purge every site for url-based rules', function () {
    config()->set('statamic.static_caching.invalidation.rules', []);
    Queue::fake();

    (new PurgingInvalidator(invalidatorFiring([]), app(UrlCollector::class)))->invalidate(Mockery::mock(Entry::class));

    Queue::assertNothingPushed();
});

it('purges the urls of one invalidation in one batched job', function () {
    Queue::fake();

    (new PurgingInvalidator(invalidatorFiring(['/a', '/b', '/a', '/c']), app(UrlCollector::class)))
        ->invalidate(Mockery::mock(Entry::class));

    Queue::assertPushed(PurgeUrlsJob::class, fn (PurgeUrlsJob $job) => $job->urls === [
        'https://example.com/a',
        'https://example.com/b',
        'https://example.com/c',
    ]);
    Queue::assertPushed(PurgeUrlsJob::class, 1);
    Queue::assertNotPushed(PurgeUrlJob::class);
});

it('still purges urls invalidated outside the invalidator one by one', function () {
    Queue::fake();

    event(new UrlInvalidated('/opera', 'https://example.com'));

    Queue::assertPushed(PurgeUrlJob::class, fn (PurgeUrlJob $job) => $job->url === 'https://example.com/opera');
});
