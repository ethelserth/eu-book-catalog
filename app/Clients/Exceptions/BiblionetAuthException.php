<?php

declare(strict_types=1);

namespace App\Clients\Exceptions;

use RuntimeException;

/**
 * Thrown when BIBLIONET authentication fails (bad credentials, expired token, etc.).
 * The caller should NOT retry automatically — human intervention is required.
 */
class BiblionetAuthException extends RuntimeException {}
