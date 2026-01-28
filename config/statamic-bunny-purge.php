<?php

return [
    'api_key' => env('BUNNY_PURGE_KEY'),
    'api_url' => env('BUNNY_PURGE_API_URL', 'https://api.bunny.net/purge'),
    'site_url' => env('BUNNY_PURGE_SITE_URL', env('APP_URL')),
];
