<?php

namespace Noo\StatamicBunnyPurge;

use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;

/**
 * Collects the URLs Statamic invalidates during one invalidator call, so
 * they are purged in batches by one job instead of one job per URL.
 */
class UrlCollector
{
    private int $depth = 0;

    /** @var array<string, true> */
    private array $urls = [];

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function collect(callable $callback): mixed
    {
        $this->depth++;

        try {
            return $callback();
        } finally {
            if (--$this->depth === 0) {
                $urls = array_keys($this->urls);
                $this->urls = [];

                if ($urls !== []) {
                    PurgeUrlsJob::dispatch($urls);
                }
            }
        }
    }

    /**
     * Add a URL to the current collection. Returns false when nothing is
     * being collected, so the caller should purge the URL itself.
     */
    public function add(string $url): bool
    {
        if ($this->depth === 0) {
            return false;
        }

        $this->urls[$url] = true;

        return true;
    }
}
