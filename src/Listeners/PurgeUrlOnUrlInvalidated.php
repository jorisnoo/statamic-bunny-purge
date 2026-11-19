<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Noo\StatamicBunnyPurge\UrlCollector;
use Statamic\Events\UrlInvalidated;

class PurgeUrlOnUrlInvalidated
{
    public function __construct(
        private readonly UrlCollector $collector
    ) {}

    public function handle(UrlInvalidated $event): void
    {
        $this->collector->add($event->url);
    }
}
