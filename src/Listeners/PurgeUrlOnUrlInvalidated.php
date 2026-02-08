<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Statamic\Events\UrlInvalidated;

class PurgeUrlOnUrlInvalidated implements ShouldBeUnique, ShouldQueue
{
    public function __construct(
        private CdnPurgeService $cdnPurgeService
    ) {}

    public function handle(UrlInvalidated $event): void
    {
        $this->cdnPurgeService->purgeUrl($event->url);
    }

    public function uniqueId(UrlInvalidated $event): string
    {
        return $event->url;
    }
}
