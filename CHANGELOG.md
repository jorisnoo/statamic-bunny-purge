# Changelog

All notable changes to this project will be documented in this file.

## [0.3.2](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.3.2) (2026-02-09)

### Bug Fixes

- purge both exact base URL and wildcard per site when purging all ([514e1fa](https://github.com/jorisnoo/statamic-bunny-purge/commit/514e1faf59075b06e8fd0aca934c46114d02dda5))
- gh actions tests ([62f19fc](https://github.com/jorisnoo/statamic-bunny-purge/commit/62f19fcd4f7e5866ef379ea5af56c4010622367d))
## [0.3.1](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.3.1) (2026-02-08)

### Bug Fixes

- drop Laravel 11 support and make URL purge listener unique per URL ([4b0f9f5](https://github.com/jorisnoo/statamic-bunny-purge/commit/4b0f9f51205d289b421f45925fb39181d59ed024))

### Code Refactoring

- extract URL purge logic into a dedicated queued job ([57478a9](https://github.com/jorisnoo/statamic-bunny-purge/commit/57478a9ae1019b002dce2acea05cd48ad2c9a9b0))
## [0.3.0](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.3.0) (2026-02-08)

### Bug Fixes

- drop Laravel 11 support and make URL purge listener unique per URL ([4b0f9f5](https://github.com/jorisnoo/statamic-bunny-purge/commit/4b0f9f51205d289b421f45925fb39181d59ed024))
## [0.2.2](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.2.2) (2026-02-07)

### Code Refactoring

- use Statamic site URLs for purge-all instead of dedicated site_url config ([eb5726b](https://github.com/jorisnoo/statamic-bunny-purge/commit/eb5726b3ea9c9eb11b9eb3d274679caca0f1170d))
- replace provider enum with config-driven CDN purge supporting custom API URL and auth type ([887ffb8](https://github.com/jorisnoo/statamic-bunny-purge/commit/887ffb8a3f5d965f38ca9424221be46a8a9345d3))
## [0.2.1](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.2.1) (2026-02-06)

### Features

- add isEnabled check and skip event registration when API key is not configured ([e0c76e1](https://github.com/jorisnoo/statamic-bunny-purge/commit/e0c76e10f14af317e92a50729d8c1b1e73ae953b))

### Bug Fixes

- make PurgeAllOnStaticCacheCleared implement ShouldBeUnique to prevent duplicate queue jobs ([b94bd1a](https://github.com/jorisnoo/statamic-bunny-purge/commit/b94bd1ad53f6500b9143bbac7b541c89c591b9a2))

### Code Refactoring

- check API key via config instead of instantiating CdnPurgeService and add service provider tests ([8c18867](https://github.com/jorisnoo/statamic-bunny-purge/commit/8c18867f5c42abdab893e559660510a3ff7eb4a7))
- replace BunnyPurgeService with provider-based CdnPurgeService supporting multiple CDN providers ([ec1ddd0](https://github.com/jorisnoo/statamic-bunny-purge/commit/ec1ddd078b831fe30a0f6acb85ef8e766d7d57e3))

### Build System

- fix PHPStan configuration ([4def46a](https://github.com/jorisnoo/statamic-bunny-purge/commit/4def46a963b7c8b25a3335e9be3b012e627f4fab))
## [0.2.0](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.2.0) (2026-02-06)

### Features

- add isEnabled check and skip event registration when API key is not configured ([e0c76e1](https://github.com/jorisnoo/statamic-bunny-purge/commit/e0c76e10f14af317e92a50729d8c1b1e73ae953b))

### Bug Fixes

- make PurgeAllOnStaticCacheCleared implement ShouldBeUnique to prevent duplicate queue jobs ([b94bd1a](https://github.com/jorisnoo/statamic-bunny-purge/commit/b94bd1ad53f6500b9143bbac7b541c89c591b9a2))

### Code Refactoring

- replace BunnyPurgeService with provider-based CdnPurgeService supporting multiple CDN providers ([058ad3c](https://github.com/jorisnoo/statamic-bunny-purge/commit/058ad3cf39ee9f2f77a050d3cf3285f709cfe92d))

### Build System

- fix PHPStan configuration ([4def46a](https://github.com/jorisnoo/statamic-bunny-purge/commit/4def46a963b7c8b25a3335e9be3b012e627f4fab))
## [0.1.1](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.1.1) (2026-01-28)

### Build System

- fix deps ([2247176](https://github.com/jorisnoo/statamic-bunny-purge/commit/2247176de6edfcc4ad1e067ef086c82ba846a761))
- add release workflow ([2b17112](https://github.com/jorisnoo/statamic-bunny-purge/commit/2b171121d5e2a5e84c6bba0c3181b710f2af1574))
## [0.1.0](https://github.com/jorisnoo/statamic-bunny-purge/releases/tag/v0.1.0) (2026-01-28)
