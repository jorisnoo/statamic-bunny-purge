<?php

return [
    'provider' => env('CDN_PURGE_PROVIDER', 'bunny'),
    'site_url' => env('CDN_PURGE_SITE_URL', env('APP_URL')),
];
