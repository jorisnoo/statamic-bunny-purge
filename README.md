# Statamic Bunny Purge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jorisnoo/statamic-bunny-purge.svg?style=flat-square)](https://packagist.org/packages/jorisnoo/statamic-bunny-purge)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jorisnoo/statamic-bunny-purge/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jorisnoo/statamic-bunny-purge/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jorisnoo/statamic-bunny-purge/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jorisnoo/statamic-bunny-purge/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jorisnoo/statamic-bunny-purge.svg?style=flat-square)](https://packagist.org/packages/jorisnoo/statamic-bunny-purge)

Automatically purge your CDN cache when Statamic content changes. Works with Bunny CDN out of the box, but supports any CDN with a purge API.

## Installation

You can install the package via composer:

```bash
composer require jorisnoo/statamic-bunny-purge
```

Add your API key to `.env`:

```env
CDN_PURGE_API_KEY=your-bunny-api-key
```

That's it — Bunny CDN is the default and works without any further configuration.

## Configuration

You can publish the config file with:

```bash
php artisan vendor:publish --tag="bunny-purge"
```

This is the contents of the published config file:

```php
return [
    'api_url' => env('CDN_PURGE_API_URL', 'https://api.bunny.net/purge'),
    'api_key' => env('CDN_PURGE_API_KEY'),
    'auth_type' => env('CDN_PURGE_AUTH_TYPE', 'access_key'),
    'purge_all_on_static_cache_cleared' => env('CDN_PURGE_ALL_ON_STATIC_CACHE_CLEARED', true),
];
```

| Key | Description | Default |
|-----|-------------|---------|
| `api_url` | The CDN purge API endpoint | `https://api.bunny.net/purge` |
| `api_key` | Your CDN API key | — |
| `auth_type` | Auth header style: `access_key` or `bearer` | `access_key` |
| `purge_all_on_static_cache_cleared` | Purge the whole site when Statamic's static cache is cleared (e.g. on deploy) | `true` |

Set `CDN_PURGE_ALL_ON_STATIC_CACHE_CLEARED=false` when your deploy already flushes the CDN pull zone, so the static-cache-clear event doesn't trigger a redundant site-wide purge. Per-URL purges on content and asset changes are unaffected.

### Using a custom CDN

Override the API URL and auth type to point at any CDN purge endpoint:

```env
CDN_PURGE_API_URL=https://cdn.example.com/api/cache/purge
CDN_PURGE_API_KEY=your-api-key
CDN_PURGE_AUTH_TYPE=bearer
```

## Usage

The package works automatically. It listens to Statamic's URL invalidation events and purges the corresponding URLs from your CDN cache whenever content changes.

### How purges are sent

- Requests carry at most 50 URLs, and exact and wildcard URLs are never sent together. Bunny rate-limits the two types separately, and wildcard purges (a URL that contains `*` or ends with `/`) have a much smaller allowance. Wildcard batches hold at most 15 URLs and are spaced 30 seconds apart; exact batches are spaced 10 seconds apart.
- A purge-all sends each site's base URL, then `<base URL>/*`. A wildcard covered by another site's wildcard (for example `/fr/*` under `/*`) is left out.

### Failures and retries

Run a queue worker (any driver other than `sync`). With a worker:

- A rate-limited purge (HTTP 429) is released back onto the queue. It waits for the time the CDN asks for, but at least as long as the rate limit needs to refill (10 seconds for exact URLs, 30 seconds for wildcards) and at most 5 minutes.
- Server errors and connection failures throw, so the queue retries them with increasing backoff.
- Jobs keep retrying for up to an hour, independent of the worker's `--tries`, and then land in `failed_jobs`.
- A purge the CDN rejects (another 4xx, such as a bad API key) is not retried. The job fails right away so it shows up in `failed_jobs`.

With the `sync` queue driver, purges run inline and cannot be released or retried. Failures are logged and the request that triggered the purge still succeeds. Requests are sent back to back, without the spacing above.

`CdnPurgeService::purgeUrl()` and `purgeAll()` still send immediately and return `false` on failure. They split requests by type but do not space or retry them.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Joris Noordermeer](https://github.com/jorisnoo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
