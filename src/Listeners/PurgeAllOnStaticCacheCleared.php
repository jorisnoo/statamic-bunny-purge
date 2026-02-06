<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Statamic\Events\StaticCacheCleared;

class PurgeAllOnStaticCacheCleared implements ShouldBeUnique, ShouldQueue
{
    public function __construct(
        private CdnPurgeService $cdnPurgeService
    ) {}

    public function handle(StaticCacheCleared $event): void
    {
        $this->cdnPurgeService->purgeAll();
    }
}
