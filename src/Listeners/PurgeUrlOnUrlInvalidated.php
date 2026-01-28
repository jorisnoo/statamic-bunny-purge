<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Noo\StatamicBunnyPurge\BunnyPurgeService;
use Statamic\Events\UrlInvalidated;

class PurgeUrlOnUrlInvalidated implements ShouldQueue
{
    public function __construct(
        private BunnyPurgeService $bunnyPurgeService
    ) {}

    public function handle(UrlInvalidated $event): void
    {
        $this->bunnyPurgeService->purgeUrl($event->url);
    }
}
