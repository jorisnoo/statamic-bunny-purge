# Statamic Bunny Purge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jorisnoo/statamic-bunny-purge.svg?style=flat-square)](https://packagist.org/packages/jorisnoo/statamic-bunny-purge)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jorisnoo/statamic-bunny-purge/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jorisnoo/statamic-bunny-purge/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jorisnoo/statamic-bunny-purge/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jorisnoo/statamic-bunny-purge/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jorisnoo/statamic-bunny-purge.svg?style=flat-square)](https://packagist.org/packages/jorisnoo/statamic-bunny-purge)

Automatically purge Bunny CDN cache when Statamic content changes.

## Installation

You can install the package via composer:

```bash
composer require jorisnoo/statamic-bunny-purge
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="statamic-bunny-purge-config"
```

This is the contents of the published config file:

```php
return [
    'api_key' => env('BUNNY_PURGE_KEY'),
    'api_url' => env('BUNNY_PURGE_API_URL', 'https://api.bunny.net/purge'),
    'site_url' => env('BUNNY_PURGE_SITE_URL', env('APP_URL')),
    'auth_type' => env('BUNNY_PURGE_AUTH_TYPE', 'bunny'), // 'bunny' or 'bearer'
];
```

## Configuration

Add the following environment variables to your `.env` file:

```env
BUNNY_PURGE_KEY=your-bunny-api-key
```

## Usage

The package works automatically. It listens to Statamic's URL invalidation events and purges the corresponding URLs from your Bunny CDN cache whenever content changes.

## Alternative Endpoints

If you're using a custom cache purging endpoint instead of the default Bunny CDN API, you can configure the package to use Bearer token authentication:

```env
BUNNY_PURGE_API_URL=https://your-custom-endpoint.com/purge
BUNNY_PURGE_AUTH_TYPE=bearer
```

This changes the `Authorization` header from `AccessKey: {key}` to `Bearer {key}`.

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
