<?php

namespace Noo\StatamicBunnyPurge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Noo\StatamicBunnyPurge\Enums\CdnProvider;

class CdnPurgeService
{
    private CdnProvider $provider;

    private ?string $apiKey;

    private string $siteUrl;

    public function __construct()
    {
        $this->provider = CdnProvider::from(config('statamic-bunny-purge.provider'));

        $providerName = $this->provider->value;

        $this->apiKey = config("services.{$providerName}.api_key");
        $this->siteUrl = rtrim(config('statamic-bunny-purge.site_url'), '/');
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
        return $this->sendPurgeRequest(["{$this->siteUrl}/*"]);
    }

    /** @param array<int, string> $urls */
    private function sendPurgeRequest(array $urls): bool
    {
        if (! $this->apiKey) {
            Log::warning('CDN purge API key not configured');

            return false;
        }

        $response = Http::withHeaders($this->provider->authHeaders($this->apiKey))
            ->post($this->provider->apiUrl(), ['urls' => $urls]);

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
}
