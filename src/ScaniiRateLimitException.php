<?php

declare(strict_types=1);

namespace Scanii;

use Throwable;

/**
 * Thrown when the Scanii API returns HTTP 429. The optional Retry-After
 * header value (in seconds) is exposed via $retryAfter when the server
 * provided one.
 *
 * Per SDK Principle 3 (integration-only), the SDK does not retry on the
 * caller's behalf — handling backoff is the caller's responsibility.
 */
class ScaniiRateLimitException extends ScaniiException
{
    public function __construct(
        string $message,
        int $statusCode = 429,
        ?string $requestId = null,
        ?string $hostId = null,
        ?string $rawResponse = null,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $requestId, $hostId, $rawResponse, $previous);
    }
}
