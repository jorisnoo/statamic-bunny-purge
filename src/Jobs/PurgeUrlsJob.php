<?php

namespace Noo\StatamicBunnyPurge\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Noo\StatamicBunnyPurge\Jobs\Concerns\SendsPurgeRequests;

/**
 * Purges a set of URLs one single-type batch at a time, queueing the rest.
 */
class PurgeUrlsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SendsPurgeRequests, SerializesModels;

    /** @var list<string> */
    public readonly array $urls;

    /**
     * @param  array<int, string>  $urls
     */
    public function __construct(array $urls)
    {
        $this->urls = array_values(array_unique($urls));
    }

    public function uniqueId(): string
    {
        $urls = $this->urls;
        sort($urls);

        return sha1(implode("\n", $urls));
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(CdnPurgeService $cdnPurgeService): void
    {
        $this->purge($cdnPurgeService, $this->urls);
    }
}
