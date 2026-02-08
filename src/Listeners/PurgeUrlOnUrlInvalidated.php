<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Statamic\Events\UrlInvalidated;

class PurgeUrlOnUrlInvalidated
{
    public function handle(UrlInvalidated $event): void
    {
        PurgeUrlJob::dispatch($event->url);
    }
}
