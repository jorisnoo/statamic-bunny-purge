<?php

use Illuminate\Http\Client\Request;
use Noo\StatamicBunnyPurge\Tests\TestCase;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use Statamic\Sites\Sites;

uses(TestCase::class)->in(__DIR__);

function fakeSite(string $absoluteUrl): StatamicSite
{
    $site = Mockery::mock(StatamicSite::class);
    $site->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);

    return $site;
}

/**
 * @param  array<string, string>  $absoluteUrls  site handle => absolute URL
 */
function fakeSites(array $absoluteUrls): void
{
    $sites = Mockery::mock(Sites::class);
    $sites->shouldReceive('all')->andReturn(collect(array_map(fakeSite(...), $absoluteUrls)));
    Site::swap($sites);
}

/**
 * @return list<string>
 */
function exactUrls(int $count, string $prefix = 'https://example.com/page-'): array
{
    return array_map(fn (int $i) => $prefix.$i, range(1, $count));
}

function nativeTarget(Request $request): string
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $parameters);

    return $parameters['url'];
}
