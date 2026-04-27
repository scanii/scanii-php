<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * Result of an asynchronous submission (processAsync, fetch). Carries only
 * the resource id; the final ScaniiProcessingResult is delivered via the
 * optional callback URL or by polling ScaniiClient::retrieve.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
final class ScaniiPendingResult extends ScaniiResult
{
    public function __construct(
        int $statusCode,
        string $rawResponse,
        public readonly string $resourceId,
        ?string $requestId = null,
        ?string $hostId = null,
        ?string $resourceLocation = null,
    ) {
        parent::__construct($statusCode, $rawResponse, $requestId, $hostId, $resourceLocation);
    }
}
