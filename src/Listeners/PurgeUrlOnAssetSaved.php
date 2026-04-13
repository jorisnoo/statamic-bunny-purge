<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Statamic\Events\AssetSaved;

class PurgeUrlOnAssetSaved
{
    public function handle(AssetSaved $event): void
    {
        $containers = config('statamic.bunny-purge.purge_asset_containers', []);

        if (! in_array($event->asset->container()->handle(), $containers)) {
            return;
        }

        $oldPath = $event->asset->getOriginal('path');
        $newPath = $event->asset->path();

        if (! $oldPath || $oldPath === $newPath) {
            return;
        }

        $oldUrl = rtrim($event->asset->container()->absoluteUrl(), '/').'/'.ltrim($oldPath, '/');

        PurgeUrlJob::dispatch($oldUrl);
    }
}
