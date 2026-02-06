# Changelog

All notable changes to this project will be documented in this file.

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
