<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\UrlCollector;
use Statamic\Events\UrlInvalidated;

class PurgeUrlOnUrlInvalidated
{
    public function __construct(
        private readonly UrlCollector $collector
    ) {}

    public function handle(UrlInvalidated $event): void
    {
        if ($this->collector->add($event->url)) {
            return;
        }

        PurgeUrlJob::dispatch($event->url);
    }
}
