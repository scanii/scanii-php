<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * Common metadata shared by every API response: request/host ids, the raw
 * response body, the HTTP status code, and (when present) a Location header.
 */
abstract class ScaniiResult
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $rawResponse,
        public readonly ?string $requestId = null,
        public readonly ?string $hostId = null,
        public readonly ?string $resourceLocation = null,
    ) {
    }
}
