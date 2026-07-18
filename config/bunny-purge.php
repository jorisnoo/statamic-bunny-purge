<?php

return [
    'api_url' => env('CDN_PURGE_API_URL', 'https://api.bunny.net/purge'),
    'api_key' => env('CDN_PURGE_API_KEY'),
    'auth_type' => env('CDN_PURGE_AUTH_TYPE', 'access_key'),
    'purge_asset_containers' => env('CDN_PURGE_ASSET_CONTAINERS')
        ? explode(',', env('CDN_PURGE_ASSET_CONTAINERS'))
        : [],

    /*
     * Purge the entire site from the CDN whenever Statamic's static cache is
     * cleared (e.g. on deploy). Disable this when your deploy already flushes
     * the CDN pull zone, to avoid a redundant purge request.
     */
    'purge_all_on_static_cache_cleared' => env('CDN_PURGE_ALL_ON_STATIC_CACHE_CLEARED', true),
];
