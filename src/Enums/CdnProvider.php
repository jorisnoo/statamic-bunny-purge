<?php

namespace Noo\StatamicBunnyPurge\Enums;

enum CdnProvider: string
{
    case Bunny = 'bunny';
    case Zentrale = 'zentrale';

    public function apiUrl(): string
    {
        return match ($this) {
            self::Bunny => 'https://api.bunny.net/purge',
            self::Zentrale => 'https://zentrale.noo.work/api/cache/purge',
        };
    }

    /** @return array<string, string> */
    public function authHeaders(string $apiKey): array
    {
        return match ($this) {
            self::Bunny => ['AccessKey' => $apiKey],
            self::Zentrale => ['Authorization' => "Bearer {$apiKey}"],
        };
    }
}
