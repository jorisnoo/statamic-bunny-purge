<?php

namespace Noo\StatamicBunnyPurge\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Noo\StatamicBunnyPurge\CdnPurgeService;

class PurgeUrlJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $url
    ) {}

    public function uniqueId(): string
    {
        return $this->url;
    }

    public function handle(CdnPurgeService $cdnPurgeService): void
    {
        $cdnPurgeService->purgeUrl($this->url);
    }
}
