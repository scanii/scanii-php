<?php

declare(strict_types=1);

namespace Scanii;

use RuntimeException;
use Throwable;

/**
 * Base exception thrown by the Scanii client whenever the API responds with
 * a non-success status code or a transport-level error occurs.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
class ScaniiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $requestId = null,
        public readonly ?string $hostId = null,
        public readonly ?string $rawResponse = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
