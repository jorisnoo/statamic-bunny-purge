<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Noo\StatamicBunnyPurge\Exceptions\PurgeFailedException;
use Noo\StatamicBunnyPurge\Jobs\PurgeAllJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;

it('sends one batch per run and queues the rest after the bucket refills', function () {
    Http::fake(['*' => Http::response('', 200)]);
    Queue::fake();

    $urls = exactUrls(120);
    (new PurgeUrlsJob($urls))->withFakeQueueInteractions()->handle(new CdnPurgeService);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['urls'] === array_slice($urls, 0, 50));

    Queue::assertPushed(PurgeUrlsJob::class, fn (PurgeUrlsJob $job) => $job->urls === array_slice($urls, 50)
        && $job->delay === CdnPurgeService::EXACT_PACING_SECONDS);
});

it('does not wait before switching to the other bucket', function () {
    Http::fake(['*' => Http::response('', 200)]);
    Queue::fake();

    (new PurgeUrlsJob(['https://example.com/opera', 'https://example.com/*']))
        ->withFakeQueueInteractions()
        ->handle(new CdnPurgeService);

    Http::assertSent(fn (Request $request) => $request['urls'] === ['https://example.com/opera']);
    Queue::assertPushed(PurgeUrlsJob::class, fn (PurgeUrlsJob $job) => $job->urls === ['https://example.com/*'] && $job->delay === 0);
});

it('purges everything in single-type batches of at most 50 urls', function () {
    config()->set('queue.default', 'sync');
    Http::fake(['*' => Http::response('', 200)]);

    $wildcards = array_map(fn (int $i) => "https://example.com/section-{$i}/*", range(1, 20));
    PurgeUrlsJob::dispatch([...$wildcards, ...exactUrls(120)]);

    $sent = Http::recorded()->map(fn (array $pair) => $pair[0]['urls']);

    expect($sent->map(fn (array $urls) => count($urls))->all())->toBe([50, 50, 20, 15, 5]);
    expect($sent->flatten()->sort()->values()->all())->toBe(collect([...$wildcards, ...exactUrls(120)])->sort()->values()->all());
});

it('releases a rate-limited job for the floored wait instead of dropping it', function () {
    Http::fake(['*' => Http::response(['type' => 'prefix', 'retry_after_seconds' => 2], 429, ['Retry-After' => '2'])]);
    Queue::fake();

    $job = (new PurgeUrlsJob(['https://example.com/*', 'https://example.com/fr/*']))->withFakeQueueInteractions();
    $job->handle(new CdnPurgeService);

    $job->assertReleased(CdnPurgeService::WILDCARD_PACING_SECONDS);
    $job->assertNotFailed();
    Queue::assertNothingPushed();
});

it('releases a rate-limited single-url job', function () {
    Http::fake(['*' => Http::response('', 429, ['Retry-After' => '45'])]);

    $job = (new PurgeUrlJob('https://example.com/opera'))->withFakeQueueInteractions();
    $job->handle(new CdnPurgeService);

    $job->assertReleased(45);
});

it('throws on a server error so the queue retries the purge', function () {
    Http::fake(['*' => Http::response('down', 503)]);

    $job = (new PurgeUrlJob('https://example.com/opera'))->withFakeQueueInteractions();

    expect(fn () => $job->handle(new CdnPurgeService))->toThrow(PurgeFailedException::class);
});

it('fails a rejected purge so it reaches failed_jobs, and still purges the rest', function () {
    Http::fake(['*' => Http::response('bad request', 400)]);
    Queue::fake();

    $urls = exactUrls(60);
    $job = (new PurgeUrlsJob($urls))->withFakeQueueInteractions();
    $job->handle(new CdnPurgeService);

    $job->assertFailed();
    Queue::assertPushed(PurgeUrlsJob::class, fn (PurgeUrlsJob $next) => $next->urls === array_slice($urls, 50));
});

it('logs instead of throwing when it cannot be retried', function () {
    Http::fake(['*' => Http::response('down', 503)]);

    // Without a queue job (dispatchSync or the sync driver) there is nothing to
    // release, and throwing would fail the request that saved the content.
    (new PurgeUrlJob('https://example.com/opera'))->handle(new CdnPurgeService);

    Http::assertSentCount(1);
});

it('keeps retrying for an hour regardless of the worker tries', function () {
    $this->freezeTime();

    foreach ([new PurgeUrlJob('https://example.com/opera'), new PurgeUrlsJob(['https://example.com/opera']), new PurgeAllJob] as $job) {
        expect($job->retryUntil()->getTimestamp())->toBe(now()->addHour()->getTimestamp())
            ->and($job->backoff())->not->toBeEmpty();
    }
});

it('purges every site with the base urls first and the wildcards next', function () {
    config()->set('queue.default', 'sync');
    Http::fake(['*' => Http::response('', 200)]);
    fakeSites(['english' => 'https://example.com/', 'french' => 'https://example.com/fr/']);

    PurgeAllJob::dispatch();

    expect(Http::recorded()->map(fn (array $pair) => $pair[0]['urls'])->all())->toBe([
        ['https://example.com', 'https://example.com/fr'],
        ['https://example.com/*'],
    ]);
});
