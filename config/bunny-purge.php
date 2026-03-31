<?php

return [
    'api_url' => env('CDN_PURGE_API_URL', 'https://api.bunny.net/purge'),
    'api_key' => env('CDN_PURGE_API_KEY'),
    'auth_type' => env('CDN_PURGE_AUTH_TYPE', 'access_key'),
    'purge_asset_containers' => env('CDN_PURGE_ASSET_CONTAINERS')
        ? explode(',', env('CDN_PURGE_ASSET_CONTAINERS'))
        : [],
];
