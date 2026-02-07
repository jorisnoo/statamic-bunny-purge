<?php

namespace Noo\StatamicBunnyPurge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;

class CdnPurgeService
{
    private string $apiUrl;

    private ?string $apiKey;

    private string $authType;

    public function __construct()
    {
        $this->apiUrl = config('statamic-bunny-purge.api_url');
        $this->apiKey = config('statamic-bunny-purge.api_key');
        $this->authType = config('statamic-bunny-purge.auth_type', 'access_key');
    }

    public function isEnabled(): bool
    {
        return filled($this->apiKey);
    }

    public function purgeUrl(string $url): bool
    {
        return $this->sendPurgeRequest([$url]);
    }

    public function purgeAll(): bool
    {
        $urls = Site::all()->map(fn (StatamicSite $site) => rtrim($site->absoluteUrl(), '/') . '/*')->values()->all();

        return $this->sendPurgeRequest($urls);
    }

    /** @param array<int, string> $urls */
    private function sendPurgeRequest(array $urls): bool
    {
        if (! $this->apiKey) {
            Log::warning('CDN purge API key not configured');

            return false;
        }

        $response = Http::withHeaders($this->authHeaders($this->apiKey))
            ->post($this->apiUrl, ['urls' => $urls]);

        if ($response->failed()) {
            Log::error('CDN purge request failed', [
                'urls' => $urls,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        Log::info('CDN purge request successful', ['urls' => $urls]);

        return true;
    }

    /** @return array<string, string> */
    private function authHeaders(string $apiKey): array
    {
        return match ($this->authType) {
            'bearer' => ['Authorization' => "Bearer {$apiKey}"],
            default => ['AccessKey' => $apiKey],
        };
    }
}
