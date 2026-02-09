<?php

use Illuminate\Support\Facades\Http;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use Statamic\Sites\Sites;

function fakeSite(string $absoluteUrl): StatamicSite
{
    $site = Mockery::mock(StatamicSite::class);
    $site->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);

    return $site;
}

it('sends both exact url and wildcard per site when purging all', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $sites = Mockery::mock(Sites::class);
    $sites->shouldReceive('all')->once()->andReturn(collect([
        'default' => fakeSite('https://example.com/'),
    ]));
    Site::swap($sites);

    $service = new CdnPurgeService;
    $result = $service->purgeAll();

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request['urls'] === [
            'https://example.com',
            'https://example.com/*',
        ];
    });
});

it('sends both exact url and wildcard for multiple sites when purging all', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $sites = Mockery::mock(Sites::class);
    $sites->shouldReceive('all')->once()->andReturn(collect([
        'english' => fakeSite('https://example.com/'),
        'french' => fakeSite('https://example.com/fr/'),
    ]));
    Site::swap($sites);

    $service = new CdnPurgeService;
    $result = $service->purgeAll();

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request['urls'] === [
            'https://example.com',
            'https://example.com/*',
            'https://example.com/fr',
            'https://example.com/fr/*',
        ];
    });
});
