<?php

namespace Noo\StatamicBunnyPurge\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Noo\StatamicBunnyPurge\Jobs\Concerns\SendsPurgeRequests;

/**
 * Purges every site: the base URLs first, then the wildcards.
 */
class PurgeAllJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SendsPurgeRequests, SerializesModels;

    /**
     * Without a TTL the lock never expires, so a worker killed before it
     * releases the lock would block every later site-wide purge.
     */
    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(CdnPurgeService $cdnPurgeService): void
    {
        $this->purge($cdnPurgeService, $cdnPurgeService->siteUrls());
    }
}
