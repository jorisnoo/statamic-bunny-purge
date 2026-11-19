<?php

namespace Noo\StatamicBunnyPurge\Jobs\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Jobs\SyncJob;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Noo\StatamicBunnyPurge\Exceptions\PurgeFailedException;
use Noo\StatamicBunnyPurge\Exceptions\RateLimitedException;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;

/**
 * Each run sends one logical batch. After a partial native batch, a new
 * job starts at the failed URL instead of replaying completed requests.
 */
trait SendsPurgeRequests
{
    /**
     * Keep retrying rate-limited and failed purges for an hour. This also
     * overrides the worker's --tries, which would otherwise fail a job on
     * its first release.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [CdnPurgeService::EXACT_PACING_SECONDS, 30, 60, 120, 300];
    }

    /**
     * @param  array<int, string>  $urls
     */
    protected function purge(CdnPurgeService $cdnPurgeService, array $urls): void
    {
        // Inline queues cannot release a job; pace independent requests in
        // process and continue after a failure.
        if ($this->job === null || $this->job instanceof SyncJob) {
            $cdnPurgeService->purgeUrls($urls);

            return;
        }

        $batches = $cdnPurgeService->batches($urls);
        $batch = array_shift($batches);

        if ($batch === null) {
            return;
        }

        try {
            // Leave time for one more 30s HTTP call in a default 60s worker.
            if ($tail = $cdnPurgeService->sendBatchUntil($batch, now()->addSeconds(15))) {
                array_unshift($batches, $tail);
            }
        } catch (PurgeFailedException $e) {
            // A gateway rejects the whole batch; Bunny only rejects one URL.
            // The batch was deduplicated, so the first failed URL identifies
            // exactly where the successful prefix ends.
            $offset = array_search($e->urls[0], $batch, true);
            if ($offset === false) {
                throw $e;
            }

            if ($e->retryable) {
                if ($offset > 0) {
                    array_unshift($batches, array_slice($batch, $offset));
                    $delay = $e instanceof RateLimitedException ? $e->retryAfter : CdnPurgeService::pacingSeconds($batch);
                    $this->dispatchRemaining($batch, $batches, $delay);

                    return;
                }

                if ($e instanceof RateLimitedException) {
                    $this->release($e->retryAfter);

                    return;
                }

                throw $e;
            }

            // Retrying a rejected request would not help: purge the rest
            // and fail this job so it shows up in failed_jobs.
            if ($tail = array_slice($batch, $offset + count($e->urls))) {
                array_unshift($batches, $tail);
            }
            $this->dispatchRemaining($batch, $batches);
            $this->fail($e);

            return;
        }

        $this->dispatchRemaining($batch, $batches);
    }

    /**
     * @param  list<string>  $sent
     * @param  list<list<string>>  $remaining
     */
    private function dispatchRemaining(array $sent, array $remaining, ?int $delay = null): void
    {
        if ($remaining === []) {
            return;
        }

        $next = $remaining[0];
        $sameBucket = CdnPurgeService::pacingSeconds($sent) === CdnPurgeService::pacingSeconds($next);

        PurgeUrlsJob::dispatch(array_merge(...$remaining))
            ->onConnection($this->connection)
            ->onQueue($this->queue)
            ->delay($delay ?? ($sameBucket ? CdnPurgeService::pacingSeconds($sent) : 0));
    }
}
