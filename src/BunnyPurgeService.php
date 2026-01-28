<?php

namespace Noo\StatamicBunnyPurge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BunnyPurgeService
{
    public function purgeUrl(string $url): bool
    {
        return $this->sendPurgeRequest([$url]);
    }

    public function purgeAll(): bool
    {
        $siteUrl = rtrim(config('statamic-bunny-purge.site_url'), '/');
        $wildcardUrl = "{$siteUrl}/*";

        return $this->sendPurgeRequest([$wildcardUrl]);
    }

    private function sendPurgeRequest(array $urls): bool
    {
        $apiKey = config('statamic-bunny-purge.api_key');

        if (! $apiKey) {
            Log::warning('Bunny purge API key not configured');

            return false;
        }

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post(config('statamic-bunny-purge.api_url'), [
            'urls' => $urls,
        ]);

        if ($response->failed()) {
            Log::error('Bunny purge request failed', [
                'urls' => $urls,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        Log::info('Bunny purge request successful', ['urls' => $urls]);

        return true;
    }
}
