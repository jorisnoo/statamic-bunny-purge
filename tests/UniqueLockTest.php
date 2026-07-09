<?php

use Carbon\Carbon;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\Listeners\PurgeAllOnStaticCacheCleared;
use Statamic\Events\StaticCacheCleared;

afterEach(fn () => Carbon::setTestNow());

function purgeAllListener(): PurgeAllOnStaticCacheCleared
{
    return (new ReflectionClass(PurgeAllOnStaticCacheCleared::class))->newInstanceWithoutConstructor();
}

it('releases the purge job lock once processing starts', function () {
    expect(new PurgeUrlJob('https://example.com/opera'))->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
});

it('releases the purge-all lock once processing starts', function () {
    expect(purgeAllListener())->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
});

it('lets a leaked purge job lock expire instead of blocking the url forever', function () {
    Queue::fake();

    $job = new PurgeUrlJob('https://example.com/opera');
    $ttl = $job->uniqueFor();

    expect($ttl)->toBeGreaterThan(0);

    // The lock a worker killed mid-job leaves behind.
    Cache::lock(UniqueLock::getKey($job), $ttl)->get();

    PurgeUrlJob::dispatch($job->url);
    Queue::assertNothingPushed();

    Carbon::setTestNow(Carbon::now()->addSeconds($ttl + 1));

    PurgeUrlJob::dispatch($job->url);
    Queue::assertPushed(PurgeUrlJob::class, 1);
});

it('lets a leaked purge-all lock expire instead of blocking every site-wide purge', function () {
    Queue::fake();

    $ttl = purgeAllListener()->uniqueFor();

    expect($ttl)->toBeGreaterThan(0);

    Cache::lock('laravel_unique_job:'.PurgeAllOnStaticCacheCleared::class.':', $ttl)->get();

    event(new StaticCacheCleared);
    Queue::assertNothingPushed();

    Carbon::setTestNow(Carbon::now()->addSeconds($ttl + 1));

    event(new StaticCacheCleared);
    Queue::assertPushed(
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === PurgeAllOnStaticCacheCleared::class,
    );
});

it('locks each url independently', function () {
    Queue::fake();

    $stuck = new PurgeUrlJob('https://example.com/opera');
    Cache::lock(UniqueLock::getKey($stuck), $stuck->uniqueFor())->get();

    PurgeUrlJob::dispatch($stuck->url);
    PurgeUrlJob::dispatch('https://example.com/ballet');

    Queue::assertPushed(PurgeUrlJob::class, fn (PurgeUrlJob $job) => $job->url === 'https://example.com/ballet');
    Queue::assertPushed(PurgeUrlJob::class, 1);
});
