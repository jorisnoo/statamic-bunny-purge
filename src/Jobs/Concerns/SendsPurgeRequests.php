<?php

namespace Noo\StatamicBunnyPurge\Jobs\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Noo\StatamicBunnyPurge\CdnPurgeService;
use Noo\StatamicBunnyPurge\Exceptions\PurgeFailedException;
use Noo\StatamicBunnyPurge\Exceptions\RateLimitedException;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlsJob;

/**
 * Each run sends a single request, so a released or retried job resends
 * exactly the URLs that were not purged yet. Remaining batches are queued
 * as a new job, delayed when they draw from the bucket just used.
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
        $batches = $cdnPurgeService->batches($urls);
        $batch = array_shift($batches);

        if ($batch === null) {
            return;
        }

        try {
            $cdnPurgeService->sendBatch($batch);
        } catch (RateLimitedException $e) {
            $this->retryLater($e, $e->retryAfter);

            return;
        } catch (PurgeFailedException $e) {
            if ($e->retryable) {
                // Let the worker retry with backoff().
                $this->retryLater($e);

                return;
            }

            // Retrying a rejected request would not help: purge the rest
            // and fail this job so it shows up in failed_jobs.
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
    private function dispatchRemaining(array $sent, array $remaining): void
    {
        if ($remaining === []) {
            return;
        }

        $next = $remaining[0];
        $sameBucket = CdnPurgeService::pacingSeconds($sent) === CdnPurgeService::pacingSeconds($next);

        PurgeUrlsJob::dispatch(array_merge(...$remaining))
            ->delay($sameBucket ? CdnPurgeService::pacingSeconds($sent) : 0);
    }

    private function retryLater(PurgeFailedException $e, ?int $delay = null): void
    {
        if (! $this->canBeRetried()) {
            // The sync driver runs jobs inline and cannot release them, and
            // throwing would fail the request that triggered the purge.
            Log::warning('CDN purge cannot be retried without a queue worker', [
                'urls' => $e->urls,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        if ($delay === null) {
            throw $e;
        }

        $this->release($delay);
    }

    private function canBeRetried(): bool
    {
        return $this->job !== null && ! $this->job instanceof SyncJob;
    }
}
