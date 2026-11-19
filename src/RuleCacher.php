<?php

namespace Noo\StatamicBunnyPurge;

use Illuminate\Http\Request;
use Statamic\StaticCaching\Cacher;

/** Observe the default invalidator's resolved rules before the origin cacher expands them. */
final class RuleCacher implements Cacher
{
    public function __construct(private Cacher $inner, private UrlCollector $collector) {}

    public function invalidateUrls($urls)
    {
        $this->inner->invalidateUrls($urls);
        $this->record($urls);
    }

    public function refreshUrls($urls)
    {
        $this->inner->refreshUrls($urls);
        $this->record($urls);
    }

    private function record(array $urls): void
    {
        // The outer decorator already schedules one complete purge for 'all'.
        if (config('statamic.static_caching.invalidation.rules') === 'all') {
            return;
        }

        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            if (str_starts_with($url, '/')) {
                // Older Statamic versions can return a site-relative rule.
                $url = rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
            }

            $this->collector->add($url);
        }
    }

    public function cachePage(Request $request, $content)
    {
        $this->inner->cachePage($request, $content);
    }

    public function hasCachedPage(Request $request)
    {
        return $this->inner->hasCachedPage($request);
    }

    public function getCachedPage(Request $request)
    {
        return $this->inner->getCachedPage($request);
    }

    public function flush()
    {
        $this->inner->flush();
    }

    public function getUrls($domain = null)
    {
        return $this->inner->getUrls($domain);
    }

    public function invalidateUrl($url)
    {
        $this->inner->invalidateUrl($url);
    }

    public function refreshUrl($url)
    {
        $this->inner->refreshUrl($url);
    }
}
