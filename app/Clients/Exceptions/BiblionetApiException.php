<?php

declare(strict_types=1);

namespace App\Clients\Exceptions;

use RuntimeException;

/**
 * Thrown for any unexpected BIBLIONET API error (5xx, malformed response, etc.).
 * Carries the HTTP status code for logging and retry decisions.
 */
class BiblionetApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
