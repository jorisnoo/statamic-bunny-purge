<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Statamic\Events\AssetReuploaded;

class PurgeUrlOnAssetReuploaded
{
    public function handle(AssetReuploaded $event): void
    {
        $containers = config('statamic.bunny-purge.purge_asset_containers', []);

        if (! in_array($event->asset->container()->handle(), $containers)) {
            return;
        }

        PurgeUrlJob::dispatch($event->asset->absoluteUrl());
    }
}
