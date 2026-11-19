<?php

namespace Noo\StatamicBunnyPurge;

use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;

use function Illuminate\Support\defer;

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
                $this->flush();
            }
        }
    }

    public function add(string $url): void
    {
        if ($this->depth === 0 && $this->urls === []) {
            // Laravel runs deferred callbacks after HTTP requests, commands
            // and individual worker jobs, not just when the process exits.
            defer(fn () => $this->flush())->always();
        }

        $this->urls[$url] = true;
    }

    private function flush(): void
    {
        if ($this->urls === []) {
            return;
        }

        $urls = array_keys($this->urls);
        $this->urls = [];
        PurgeUrlsJob::dispatch($urls);
    }
}
