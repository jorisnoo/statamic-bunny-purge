<?php

namespace Noo\StatamicBunnyPurge\Exceptions;

use RuntimeException;
use Throwable;

class PurgeFailedException extends RuntimeException
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        string $message,
        public readonly array $urls = [],
        public readonly ?int $status = null,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }
}
