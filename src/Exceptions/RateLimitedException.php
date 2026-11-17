<?php

namespace Noo\StatamicBunnyPurge\Exceptions;

class RateLimitedException extends PurgeFailedException
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        string $message,
        array $urls,
        public readonly int $retryAfter,
    ) {
        parent::__construct($message, $urls, 429, true);
    }
}
