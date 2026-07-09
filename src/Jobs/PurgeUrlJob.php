<?php

namespace Noo\StatamicBunnyPurge\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Noo\StatamicBunnyPurge\CdnPurgeService;

class PurgeUrlJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $url
    ) {}

    public function uniqueId(): string
    {
        return $this->url;
    }

    /**
     * Without a TTL the lock never expires, so a worker killed before it
     * releases the lock would block this URL from ever being purged again.
     */
    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(CdnPurgeService $cdnPurgeService): void
    {
        $cdnPurgeService->purgeUrl($this->url);
    }
}
