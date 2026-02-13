<?php

return [
    'api_url' => env('CDN_PURGE_API_URL', 'https://api.bunny.net/purge'),
    'api_key' => env('CDN_PURGE_API_KEY'),
    'auth_type' => env('CDN_PURGE_AUTH_TYPE', 'access_key'),
];
