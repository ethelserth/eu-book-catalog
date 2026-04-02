<?php

declare(strict_types=1);

namespace App\Clients\Exceptions;

use RuntimeException;

/**
 * Thrown when BIBLIONET returns HTTP 429 (Too Many Requests).
 * The caller should back off and retry after $retryAfter seconds.
 */
class BiblionetRateLimitException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfter = 60,
        string $message = 'BIBLIONET rate limit exceeded',
    ) {
        parent::__construct($message);
    }
}
