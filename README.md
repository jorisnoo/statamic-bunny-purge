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
php artisan vendor:publish --tag="statamic-bunny-purge-config"
```

This is the contents of the published config file:

```php
return [
    'api_url' => env('CDN_PURGE_API_URL', 'https://api.bunny.net/purge'),
    'api_key' => env('CDN_PURGE_API_KEY'),
    'auth_type' => env('CDN_PURGE_AUTH_TYPE', 'access_key'),
    'site_url' => env('CDN_PURGE_SITE_URL', env('APP_URL')),
];
```

| Key | Description | Default |
|-----|-------------|---------|
| `api_url` | The CDN purge API endpoint | `https://api.bunny.net/purge` |
| `api_key` | Your CDN API key | — |
| `auth_type` | Auth header style: `access_key` or `bearer` | `access_key` |
| `site_url` | The site URL used when purging all cache | `APP_URL` |

### Using a custom CDN

Override the API URL and auth type to point at any CDN purge endpoint:

```env
CDN_PURGE_API_URL=https://cdn.example.com/api/cache/purge
CDN_PURGE_API_KEY=your-api-key
CDN_PURGE_AUTH_TYPE=bearer
```

## Usage

The package works automatically. It listens to Statamic's URL invalidation events and purges the corresponding URLs from your CDN cache whenever content changes.

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
