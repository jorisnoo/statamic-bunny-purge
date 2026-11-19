<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Noo\StatamicBunnyPurge\Exceptions\PurgeFailedException;
use Noo\StatamicBunnyPurge\Exceptions\RateLimitedException;

it('sends the exact url and the wildcard per site in separate requests when purging all', function () {
    Http::fake(['*' => Http::response('', 200)]);
    fakeSites(['default' => 'https://example.com/']);

    expect((new CdnPurgeService)->purgeAll())->toBeTrue();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => nativeTarget($request) === 'https://example.com');
    Http::assertSent(fn (Request $request) => nativeTarget($request) === 'https://example.com/*');
});

it('drops a wildcard another site already covers when purging all', function () {
    fakeSites([
        'english' => 'https://example.com/',
        'french' => 'https://example.com/fr/',
        'german' => 'https://example.de/',
    ]);

    expect((new CdnPurgeService)->siteUrls())->toBe([
        'https://example.com',
        'https://example.com/fr',
        'https://example.de',
        'https://example.com/*',
        'https://example.de/*',
    ]);
});

it('never sends more than 50 urls in one request', function () {
    expect(array_map('count', (new CdnPurgeService)->batches(exactUrls(120))))->toBe([50, 50, 20]);
});

it('never mixes exact and wildcard urls in one request', function () {
    $wildcards = array_map(fn (int $i) => "https://example.com/section-{$i}/*", range(1, 20));
    $batches = (new CdnPurgeService)->batches([
        ...$wildcards,
        'https://example.com/',
        ...exactUrls(60),
    ]);

    expect(array_map('count', $batches))->toBe([50, 10, 15, 6]);

    foreach ($batches as $batch) {
        $types = array_unique(array_map(CdnPurgeService::isWildcard(...), $batch));

        expect($types)->toHaveCount(1);
    }

    // A URL ending in a slash draws from Bunny's wildcard bucket.
    expect($batches[3])->toContain('https://example.com/');
});

it('uses the documented native POST query and preserves nested URL encoding', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $target = 'https://example.com/my%20opera/?x=a+b&x=%2F';
    (new CdnPurgeService)->sendBatch([$target]);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.bunny.net/purge?url=')
        && nativeTarget($request) === $target
        && $request->method() === 'POST'
        && $request->hasHeader('AccessKey', 'test-key')
        && ! $request->hasHeader('Authorization')
        && $request->body() === '');
});

it('throws a rate limit with the wait floored to the bucket refill window', function (array $batch, array $headers, array $body, int $expected) {
    Http::fake(['*' => Http::response($body, 429, $headers)]);

    try {
        (new CdnPurgeService)->sendBatch($batch);
        $this->fail('Expected a rate limit exception');
    } catch (RateLimitedException $e) {
        expect($e->retryAfter)->toBe($expected)
            ->and($e->retryable)->toBeTrue()
            ->and($e->urls)->toBe($batch);
    }
})->with([
    'wildcard, short Retry-After' => [['https://example.com/*'], ['Retry-After' => '2'], ['type' => 'prefix', 'retry_after_seconds' => 2], 30],
    'exact, short Retry-After' => [['https://example.com/opera'], ['Retry-After' => '1'], ['type' => 'exact', 'retry_after_seconds' => 1], 10],
    'longer body wait' => [['https://example.com/opera'], ['Retry-After' => '5'], ['retry_after_seconds' => 90], 90],
    'no wait given' => [['https://example.com/opera'], [], [], 60],
    'capped' => [['https://example.com/opera'], ['Retry-After' => '3600'], [], 300],
]);

it('marks server errors and connection failures as retryable but not client errors', function ($response, bool $retryable) {
    Http::fake(['*' => $response]);

    try {
        (new CdnPurgeService)->sendBatch(['https://example.com/opera']);
        $this->fail('Expected a purge exception');
    } catch (PurgeFailedException $e) {
        expect($e)->not->toBeInstanceOf(RateLimitedException::class)
            ->and($e->retryable)->toBe($retryable);
    }
})->with([
    '500' => [fn () => Http::response('down', 500), true],
    'connection' => [fn () => fn () => throw new ConnectionException('timed out'), true],
    '401' => [fn () => Http::response('unauthorized', 401), false],
]);

it('reports a failed purge as false from purgeUrl', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect((new CdnPurgeService)->purgeUrl('https://example.com/opera'))->toBeFalse();
});

it('preserves JSON batches and normalized bearer auth for a custom gateway', function () {
    config(['statamic.bunny-purge.api_url' => 'https://gateway.example.com/purge']);
    config(['statamic.bunny-purge.auth_type' => ' Bearer ']);
    Http::fake();
    (new CdnPurgeService)->sendBatch(exactUrls(3));
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://gateway.example.com/purge'
        && $request->data() === ['urls' => exactUrls(3)]
        && $request->hasHeader('Authorization', 'Bearer test-key') && ! $request->hasHeader('AccessKey'));
});

it('rejects unsupported auth before transmitting credentials', function () {
    config(['statamic.bunny-purge.auth_type' => 'unknown']);
    Http::fake();
    expect((new CdnPurgeService)->purgeUrl('https://example.com/page'))->toBeFalse();
    Http::assertNothingSent();
});

it('refuses invalid native credentials and transport before sending', function (string $scheme, string $auth) {
    config(['statamic.bunny-purge.auth_type' => $auth, 'statamic.bunny-purge.api_url' => "$scheme://api.bunny.net/purge"]);
    Http::fake();
    expect((new CdnPurgeService)->purgeUrl('https://example.com/page'))->toBeFalse();
    Http::assertNothingSent();
})->with([['https', 'bearer'], ['http', 'access_key']]);

it('paces inline native requests after rate limits without dropping unattempted URLs', function () {
    Http::fakeSequence()->push('', 429, ['Retry-After' => '71'])->push('')->push('');
    $urls = ['https://example.com/blog/?q=1', 'https://example.com/news/*', 'https://example.com/files/*'];
    expect((new CdnPurgeService)->purgeUrls($urls))->toBeFalse();
    expect(Http::recorded()->map(fn ($pair) => nativeTarget($pair[0]))->all())->toBe($urls);
    Sleep::assertSequence([Sleep::sleep(71)]);
});
