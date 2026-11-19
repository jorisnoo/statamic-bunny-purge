<?php

namespace Noo\StatamicBunnyPurge;

use DateTimeInterface;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Noo\StatamicBunnyPurge\Exceptions\PurgeFailedException;
use Noo\StatamicBunnyPurge\Exceptions\RateLimitedException;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use Throwable;

class CdnPurgeService
{
    /**
     * Logical batches fit Bunny's per-account, per-type burst allowances:
     * exact purges refill at 5/s; prefix purges refill at 0.5/s. The native
     * API requires one request per URL. Batch-compatible gateways accept
     * the whole logical batch as JSON.
     */
    public const EXACT_BATCH_LIMIT = 50;

    public const WILDCARD_BATCH_LIMIT = 15;

    /** Seconds the exact bucket needs to refill a full batch (50 at 5/s). */
    public const EXACT_PACING_SECONDS = 10;

    /** Seconds the wildcard bucket needs to refill a full batch (15 at 0.5/s). */
    public const WILDCARD_PACING_SECONDS = 30;

    /** Wait used on a 429 that names no wait of its own. */
    public const DEFAULT_RATE_LIMIT_BACKOFF_SECONDS = 60;

    /** Cap on the wait a 429 asks for. */
    public const MAX_RATE_LIMIT_BACKOFF_SECONDS = 300;

    private string $apiUrl;

    private ?Uri $bunnyEndpoint;

    private ?string $apiKey;

    private string $authType;

    public function __construct()
    {
        $this->apiUrl = config('statamic.bunny-purge.api_url');
        $endpoint = new Uri($this->apiUrl);
        $this->bunnyEndpoint = rtrim($endpoint->getHost(), '.') === 'api.bunny.net' ? $endpoint : null;
        $this->apiKey = config('statamic.bunny-purge.api_key');
        $this->authType = strtolower(trim(config('statamic.bunny-purge.auth_type', 'access_key')));
    }

    public function isEnabled(): bool
    {
        return filled($this->apiKey);
    }

    public function purgeUrl(string $url): bool
    {
        return $this->purgeUrls([$url]);
    }

    /**
     * Purge every site in this process, without queueing retries.
     */
    public function purgeAll(): bool
    {
        return $this->purgeUrls($this->siteUrls());
    }

    /**
     * Send every batch in this process and report whether all succeeded.
     * Failures are logged; independent remaining requests are still sent.
     *
     * @param  array<int, string>  $urls
     */
    public function purgeUrls(array $urls): bool
    {
        $allSucceeded = true;
        $previousPace = null;
        $wait = 0;

        foreach ($this->batches($urls) as $batch) {
            $pace = self::pacingSeconds($batch);
            foreach ($this->requests($batch) as $request) {
                if ($previousPace === $pace && $wait > 0) {
                    Sleep::sleep($wait);
                }
                $previousPace = $pace;
                $wait = 0;

                try {
                    $this->sendRequest($request);
                } catch (PurgeFailedException $e) {
                    $allSucceeded = false;
                    $wait = $e instanceof RateLimitedException ? $e->retryAfter : 0;
                }
            }

            $wait = max($pace, $wait);
        }

        return $allSucceeded;
    }

    /**
     * The base URL and a wildcard for every site. A wildcard already covered
     * by another site's wildcard (a subdirectory multisite) is dropped, as it
     * would only use up the small wildcard bucket.
     *
     * @return list<string>
     */
    public function siteUrls(): array
    {
        $baseUrls = Site::all()
            ->map(fn (StatamicSite $site) => rtrim($site->absoluteUrl(), '/'))
            ->unique()
            ->values();

        $wildcardBases = $baseUrls->reject(
            fn (string $url) => $baseUrls->contains(
                fn (string $other) => $other !== $url && str_starts_with($url, "{$other}/")
            )
        );

        return [
            ...$baseUrls->all(),
            ...$wildcardBases->map(fn (string $url) => "{$url}/*")->all(),
        ];
    }

    public static function isWildcard(string $url): bool
    {
        return str_contains($url, '*') || str_ends_with((string) parse_url($url, PHP_URL_PATH), '/');
    }

    /**
     * Split URLs into requests of a single type, exact URLs first.
     *
     * @param  array<int, string>  $urls
     * @return list<list<string>>
     */
    public function batches(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls, 'filled')));

        $wildcards = array_values(array_filter($urls, [self::class, 'isWildcard']));
        $exact = array_values(array_diff($urls, $wildcards));

        return [
            ...array_chunk($exact, self::EXACT_BATCH_LIMIT),
            ...array_chunk($wildcards, self::WILDCARD_BATCH_LIMIT),
        ];
    }

    /**
     * Seconds to wait before sending another batch of the same type.
     *
     * @param  array<int, string>  $batch
     */
    public static function pacingSeconds(array $batch): int
    {
        foreach ($batch as $url) {
            if (self::isWildcard($url)) {
                return self::WILDCARD_PACING_SECONDS;
            }
        }

        return self::EXACT_PACING_SECONDS;
    }

    /**
     * Send one logical batch, stopping at the first failed wire request.
     *
     * @param  list<string>  $urls
     *
     * @throws RateLimitedException when the CDN answers 429
     * @throws PurgeFailedException when the request fails for any other reason
     */
    public function sendBatch(array $urls): void
    {
        $this->sendBatchUntil($urls, null);
    }

    /**
     * @param  list<string>  $urls
     * @return list<string> Unattempted URLs after the deadline.
     */
    public function sendBatchUntil(array $urls, ?DateTimeInterface $deadline): array
    {
        foreach ($this->requests($urls) as $index => $request) {
            if ($index > 0 && $deadline !== null && now()->gte($deadline)) {
                return array_slice($urls, $index);
            }
            $this->sendRequest($request);
        }

        return [];
    }

    /**
     * @param  list<string>  $urls
     * @return iterable<list<string>>
     */
    private function requests(array $urls): iterable
    {
        if ($this->bunnyEndpoint === null) {
            yield $urls;
        } else {
            foreach ($urls as $url) {
                yield [$url];
            }
        }
    }

    /** @param list<string> $urls */
    private function sendRequest(array $urls): void
    {
        if (! $this->apiKey) {
            Log::warning('CDN purge API key not configured');

            throw new PurgeFailedException('CDN purge API key not configured', $urls);
        }

        if ($this->bunnyEndpoint !== null && ($this->bunnyEndpoint->getScheme() !== 'https' || $this->authType !== 'access_key')) {
            Log::error('The Bunny Core API requires HTTPS and access_key authentication');

            throw new PurgeFailedException('The Bunny Core API requires HTTPS and access_key authentication', $urls);
        }

        $headers = $this->authHeaders($this->apiKey);
        if ($headers === null) {
            Log::error('Invalid CDN purge auth_type: expected access_key or bearer');

            throw new PurgeFailedException('Invalid CDN purge auth_type', $urls);
        }

        try {
            $client = Http::withHeaders($headers)->withoutRedirecting()->connectTimeout(10)->timeout(30);
            $response = $this->bunnyEndpoint === null
                ? $client->post($this->apiUrl, ['urls' => $urls])
                : $client->send('POST', (string) Uri::withQueryValue($this->bunnyEndpoint, 'url', rawurlencode($urls[0])));
        } catch (Throwable $e) {
            Log::error('CDN purge request threw', [
                'urls' => $urls,
                'exception' => $e->getMessage(),
            ]);

            throw new PurgeFailedException("CDN purge request failed: {$e->getMessage()}", $urls, retryable: true, previous: $e);
        }

        if ($response->status() === 429) {
            // The advertised wait can be far shorter than the bucket needs to
            // refill a batch (~2s for a drained wildcard bucket that needs
            // ~30s), so floor it to the batch type's refill window.
            $retryAfter = max($this->retryAfterSeconds($response), self::pacingSeconds($urls));

            Log::warning('CDN purge request rate-limited', [
                'urls' => $urls,
                'retry_after' => $retryAfter,
                'body' => $response->body(),
            ]);

            throw new RateLimitedException("CDN purge rate-limited; retry in {$retryAfter}s", $urls, $retryAfter);
        }

        if (! $response->successful()) {
            Log::error('CDN purge request failed', [
                'urls' => $urls,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new PurgeFailedException(
                "CDN purge request failed with status {$response->status()}: {$response->body()}",
                $urls,
                $response->status(),
                retryable: $response->serverError(),
            );
        }

        Log::info('CDN purge request successful', ['urls' => $urls]);
    }

    private function retryAfterSeconds(Response $response): int
    {
        $header = $response->header('Retry-After');

        $fromHeader = is_numeric($header)
            ? (int) $header
            : (($timestamp = strtotime($header)) !== false ? $timestamp - time() : 0);

        $fromBody = (int) $response->json('retry_after_seconds');

        $seconds = max($fromHeader, $fromBody);

        if ($seconds <= 0) {
            return self::DEFAULT_RATE_LIMIT_BACKOFF_SECONDS;
        }

        return min($seconds, self::MAX_RATE_LIMIT_BACKOFF_SECONDS);
    }

    /** @return array<string, string>|null */
    private function authHeaders(string $apiKey): ?array
    {
        return match ($this->authType) {
            'bearer' => ['Authorization' => "Bearer {$apiKey}"],
            'access_key' => ['AccessKey' => $apiKey],
            default => null,
        };
    }
}
