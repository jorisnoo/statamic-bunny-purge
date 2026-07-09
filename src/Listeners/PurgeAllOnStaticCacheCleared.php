<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Statamic\Events\StaticCacheCleared;

class PurgeAllOnStaticCacheCleared implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    public function __construct(
        private CdnPurgeService $cdnPurgeService
    ) {}

    /**
     * Without a TTL the lock never expires, so a worker killed before it
     * releases the lock would block every later site-wide purge.
     */
    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(StaticCacheCleared $event): void
    {
        $this->cdnPurgeService->purgeAll();
    }
}
