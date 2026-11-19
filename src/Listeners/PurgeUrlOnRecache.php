<?php

namespace Noo\StatamicBunnyPurge\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Noo\StatamicBunnyPurge\UrlCollector;
use Statamic\StaticCaching\StaticCacheManager;

final class PurgeUrlOnRecache
{
    public function handle(RequestHandled $event): void
    {
        if (! config('statamic.static_caching.background_recache') || ! $event->response->isSuccessful() || ! $event->request->isMethod('GET')) {
            return;
        }

        $manager = app(StaticCacheManager::class);
        $parameter = $manager->recacheTokenParameter();
        $token = $event->request->query()[$parameter] ?? null;
        if (! is_string($token) || ! $manager->checkRecacheToken($token)) {
            return;
        }

        // A deferred warmer can finish after the initial rule purge. Evict
        // once the origin has fresh content, never forwarding the warm token.
        app(UrlCollector::class)->add($event->request->fullUrlWithoutQuery($parameter));
    }
}
