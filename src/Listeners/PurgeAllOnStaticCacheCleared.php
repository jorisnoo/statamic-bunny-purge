<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Noo\StatamicBunnyPurge\BunnyPurgeService;
use Statamic\Events\StaticCacheCleared;

class PurgeAllOnStaticCacheCleared implements ShouldBeUnique, ShouldQueue
{
    public function __construct(
        private BunnyPurgeService $bunnyPurgeService
    ) {}

    public function handle(StaticCacheCleared $event): void
    {
        $this->bunnyPurgeService->purgeAll();
    }
}
