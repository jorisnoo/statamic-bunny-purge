<?php

namespace Noo\StatamicBunnyPurge;

use Noo\StatamicBunnyPurge\Jobs\PurgeAllJob;
use Statamic\StaticCaching\Invalidator;

/**
 * Wraps Statamic's configured invalidator. URLs it invalidates are purged
 * together in one batched job. With invalidation rules set to 'all',
 * Statamic flushes (or recaches) the whole static cache without firing
 * UrlInvalidated or StaticCacheCleared, so every site is purged instead.
 */
class PurgingInvalidator implements Invalidator
{
    public function __construct(
        private readonly Invalidator $invalidator,
        private readonly UrlCollector $collector,
    ) {}

    public function invalidate($item)
    {
        return $this->purgeAfter(fn () => $this->invalidator->invalidate($item));
    }

    /**
     * Statamic 6 calls this for saves; Statamic 5's interface has no refresh().
     */
    public function refresh($item)
    {
        return $this->purgeAfter(fn () => method_exists($this->invalidator, 'refresh')
            ? $this->invalidator->refresh($item)
            : $this->invalidator->invalidate($item));
    }

    public function inner(): Invalidator
    {
        return $this->invalidator;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->invalidator->{$method}(...$arguments);
    }

    private function purgeAfter(callable $callback): mixed
    {
        $result = $this->collector->collect($callback);

        if (config('statamic.static_caching.invalidation.rules') === 'all') {
            PurgeAllJob::dispatch();
        }

        return $result;
    }
}
