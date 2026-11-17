<?php

use Carbon\Carbon;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Noo\StatamicBunnyPurge\Jobs\PurgeAllJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;
use Statamic\Events\StaticCacheCleared;

afterEach(fn () => Carbon::setTestNow());

it('releases the purge job lock once processing starts', function () {
    expect(new PurgeUrlJob('https://example.com/opera'))->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
});

it('releases the purge-all and batch locks once processing starts', function () {
    expect(new PurgeAllJob)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and(new PurgeUrlsJob(['https://example.com/opera']))->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
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

    $job = new PurgeAllJob;
    $ttl = $job->uniqueFor();

    expect($ttl)->toBeGreaterThan(0);

    Cache::lock(UniqueLock::getKey($job), $ttl)->get();

    event(new StaticCacheCleared);
    Queue::assertNothingPushed();

    Carbon::setTestNow(Carbon::now()->addSeconds($ttl + 1));

    event(new StaticCacheCleared);
    Queue::assertPushed(PurgeAllJob::class, 1);
});

it('collapses repeated batches of the same urls', function () {
    Queue::fake();

    PurgeUrlsJob::dispatch(['https://example.com/opera', 'https://example.com/ballet']);
    PurgeUrlsJob::dispatch(['https://example.com/ballet', 'https://example.com/opera']);
    PurgeUrlsJob::dispatch(['https://example.com/opera']);

    Queue::assertPushed(PurgeUrlsJob::class, 2);
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
