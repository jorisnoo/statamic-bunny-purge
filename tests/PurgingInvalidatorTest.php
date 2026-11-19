<?php

use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Noo\StatamicBunnyPurge\Jobs\PurgeAllJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;
use Noo\StatamicBunnyPurge\PurgingInvalidator;
use Noo\StatamicBunnyPurge\UrlCollector;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Forms\Form;
use Statamic\Events\UrlInvalidated;
use Statamic\Facades\URL;
use Statamic\StaticCaching\Cacher;
use Statamic\StaticCaching\Cachers\ApplicationCacher;
use Statamic\StaticCaching\DefaultInvalidator;
use Statamic\StaticCaching\Invalidate;
use Statamic\StaticCaching\Invalidator;
use Symfony\Component\HttpFoundation\Response;

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

    Http::assertSent(fn (Request $request) => nativeTarget($request) === 'https://example.com');
    Http::assertSent(fn (Request $request) => nativeTarget($request) === 'https://example.com/*');
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

it('batches direct invalidations at each worker or request boundary', function (bool $worker) {
    Queue::fake();
    $complete = function () use ($worker) {
        if ($worker) {
            $job = Mockery::mock(Job::class);
            $job->shouldReceive('hasFailed')->andReturn(false);
            event(new JobAttempted('database', $job));
        } else {
            (new InvokeDeferredCallbacks)->terminate(HttpRequest::create('/'), new Response);
        }
    };

    foreach (['/a', '/a', '/b'] as $url) {
        event(new UrlInvalidated($url, 'https://example.com'));
    }
    Queue::assertNothingPushed();
    $complete();
    Queue::assertPushed(PurgeUrlsJob::class, fn ($job) => $job->urls === ['https://example.com/a', 'https://example.com/b']);

    event(new UrlInvalidated('/c', 'https://example.com'));
    $complete();
    Queue::assertPushed(PurgeUrlsJob::class, fn ($job) => $job->urls === ['https://example.com/c']);
    Queue::assertPushed(PurgeUrlsJob::class, 2);
})->with([true, false]);

it('purges resolved default rules even when the cacher fires no URL events', function (bool $refresh) {
    if ($refresh && ! method_exists(Invalidator::class, 'refresh')) {
        $this->markTestSkipped('Background recache was introduced in Statamic 6.');
    }

    Queue::fake();
    $urls = ['https://example.com/list', 'https://example.com/blog/*'];
    config(['statamic.static_caching.invalidation.rules' => ['forms' => ['contact' => ['urls' => $urls]]]]);
    config(['statamic.static_caching.background_recache' => $refresh]);
    fakeSites(['en' => 'https://example.com']);
    URL::shouldReceive('isAbsolute')->andReturn(true);
    URL::shouldReceive('tidy')->andReturnUsing(fn ($url) => $url);
    $cacher = Mockery::mock(ApplicationCacher::class);
    $cacher->shouldReceive($refresh ? 'refreshUrls' : 'invalidateUrls')->once()->with($urls);
    $this->app->instance(Cacher::class, $cacher);
    $form = Mockery::mock(Form::class);
    $form->shouldReceive('handle')->andReturn('contact');

    $refresh ? app(Invalidator::class)->refresh($form) : app(Invalidator::class)->invalidate($form);

    Queue::assertPushed(PurgeUrlsJob::class, fn ($job) => $job->urls === $urls);
    Queue::assertPushed(PurgeUrlsJob::class, 1);
})->with([false, true]);

it('purges a successfully rewarmed URL after the origin responds without sending the warm secret', function () {
    if (! method_exists(Invalidator::class, 'refresh')) {
        $this->markTestSkipped('Background recache was introduced in Statamic 6.');
    }

    config(['statamic.static_caching.background_recache' => true, 'statamic.static_caching.recache_token' => 'warm-token']);
    Queue::fake();
    foreach ([['wrong', 200], ['warm-token', 500], ['warm-token', 200]] as [$token, $status]) {
        $request = HttpRequest::create("https://example.com:8080/report?page=2&__recache={$token}");
        $response = new Response('', $status);
        event(new RequestHandled($request, $response));
        (new InvokeDeferredCallbacks)->terminate($request, $response);
        if ($token !== 'warm-token' || $status !== 200) {
            Queue::assertNothingPushed();
        }
    }

    Queue::assertPushed(PurgeUrlsJob::class, fn ($job) => $job->urls === ['https://example.com:8080/report?page=2']);
    Queue::assertPushed(PurgeUrlsJob::class, 1);
});
