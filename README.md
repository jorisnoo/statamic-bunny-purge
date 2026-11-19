# Statamic Bunny Purge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jorisnoo/statamic-bunny-purge.svg?style=flat-square)](https://packagist.org/packages/jorisnoo/statamic-bunny-purge)
[![Package checks](https://img.shields.io/github/actions/workflow/status/jorisnoo/statamic-bunny-purge/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jorisnoo/statamic-bunny-purge/actions/workflows/tests.yml)
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

Bunny is the default endpoint. Content saves require a [Statamic static caching strategy](https://statamic.dev/static-caching) and invalidation rules; without a strategy, Statamic does not run its content invalidator. To purge public assets, set `CDN_PURGE_ASSET_CONTAINERS=assets,downloads` to the container handles you serve through the CDN. Run a persistent queue worker for retries and pacing.

## Configuration

You can publish the config file with:

```bash
php artisan vendor:publish --tag="bunny-purge"
```

The published file reads the following settings (for example, `api_url` reads `CDN_PURGE_API_URL`):

| Key | Description | Default |
|-----|-------------|---------|
| `api_url` | The CDN purge API endpoint | `https://api.bunny.net/purge` |
| `api_key` | Your CDN API key | — |
| `auth_type` | Auth header style: `access_key` or `bearer` | `access_key` |
| `purge_asset_containers` | Comma-separated public container handles (`CDN_PURGE_ASSET_CONTAINERS`); private assets are skipped | None |
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

With the default invalidator, resolved rules are sent to the CDN before their wildcard coverage is lost in Statamic's local index. A custom invalidator remains in control; its `UrlInvalidated` events are batched, but rules it never reports cannot be inferred.

### How purges are sent

- The URLs Statamic invalidates for one change are purged together by one queued job, instead of one job per URL.
- Direct `UrlInvalidated` events are deduplicated and flushed at the end of the current request, Artisan command or queue job.
- Statamic 6 background recache purges the resolved rules even when no local page is cached. After a successful authenticated warm request, the refreshed URL is purged again so the CDN cannot retain a copy it fetched from the old origin cache.
- With `static_caching.invalidation.rules` set to `'all'`, Statamic flushes its whole static cache without firing an invalidation event. The package then purges every site, the same way it does when the static cache is cleared.
- Requests carry at most 50 URLs, and exact and wildcard URLs are never sent together. Bunny rate-limits the two types separately, and wildcard purges (a URL that contains `*` or ends with `/`) have a much smaller allowance. Wildcard batches hold at most 15 URLs and are spaced 30 seconds apart; exact batches are spaced 10 seconds apart.
- A purge-all sends each site's base URL, then `<base URL>/*`. A wildcard covered by another site's wildcard (for example `/fr/*` under `/*`) is left out.

### Asset and query variants

Renaming/moving a public asset purges its previous public URL. Reupload and deletion purge the original file URL. The package does not enumerate Glide derivatives, separately signed URLs or arbitrary query-string cache keys. If your zone varies on those keys, configure versioned image URLs or dispatch `PurgeUrlsJob::dispatch([...])` with the actual derivative URLs or a narrowly scoped CDN prefix from your own asset listener. See [Bunny's purge types](https://docs.bunny.net/cdn/purge-cache) before using a prefix.

### Failures and retries

Run a queue worker with a persistent driver such as Redis or database. With a worker:

- A rate-limited purge (HTTP 429) is released back onto the queue. It waits for the time the CDN asks for, but at least as long as the rate limit needs to refill (10 seconds for exact URLs, 30 seconds for wildcards) and at most 5 minutes.
- Server errors and connection failures throw, so the queue retries them with increasing backoff.
- Jobs keep retrying for up to an hour, independent of the worker's `--tries`, and then land in `failed_jobs`.
- A purge the CDN rejects (another 4xx, such as a bad API key) is not retried. The job fails right away so it shows up in `failed_jobs`.

The `sync` driver (and Laravel's in-process `deferred` queue) cannot release or retry a request. Each independent batch is attempted, including later wildcard batches after an earlier error. Failures are logged without failing the editor's request; there is no pacing.

`CdnPurgeService::purgeUrl()` and `purgeAll()` still send immediately and return `false` on failure. They split requests by type but do not space or retry them.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Joris Noordermeer](https://github.com/jorisnoo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
