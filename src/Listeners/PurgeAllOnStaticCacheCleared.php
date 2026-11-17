<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Noo\StatamicBunnyPurge\Jobs\PurgeAllJob;
use Statamic\Events\StaticCacheCleared;

/**
 * Dispatches a unique PurgeAllJob rather than being a unique queued listener
 * itself, which Laravel only supports from 12.50.
 */
class PurgeAllOnStaticCacheCleared
{
    public function handle(StaticCacheCleared $event): void
    {
        PurgeAllJob::dispatch();
    }
}
