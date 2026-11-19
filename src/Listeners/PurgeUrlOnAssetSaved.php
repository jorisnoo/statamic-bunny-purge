<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Statamic\Events\AssetSaved;

class PurgeUrlOnAssetSaved
{
    public function handle(AssetSaved $event): void
    {
        $containers = config('statamic.bunny-purge.purge_asset_containers', []);

        if (! in_array($event->asset->container()->handle(), $containers, true)) {
            return;
        }

        $oldPath = $event->asset->getOriginal('path');
        $newPath = $event->asset->path();

        if (! $oldPath || $oldPath === $newPath) {
            return;
        }

        // Let the installed Statamic version encode the previous path exactly
        // as it did when the asset was public. Never mutate the saved asset.
        $original = clone $event->asset;
        if ($url = $original->path($oldPath)->absoluteUrl()) {
            PurgeUrlJob::dispatch($url);
        }
    }
}
