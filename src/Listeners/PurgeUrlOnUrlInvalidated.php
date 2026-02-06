<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Statamic\Events\UrlInvalidated;

class PurgeUrlOnUrlInvalidated implements ShouldQueue
{
    public function __construct(
        private CdnPurgeService $cdnPurgeService
    ) {}

    public function handle(UrlInvalidated $event): void
    {
        $this->cdnPurgeService->purgeUrl($event->url);
    }
}
