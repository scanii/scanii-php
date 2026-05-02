<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * Result of ScaniiClient::retrieveTrace — ordered processing events for a scan.
 *
 * This is a v2.2 preview surface; the API shape may shift before it is marked
 * stable.
 *
 * @see https://scanii.github.io/openapi/v22/ — GET /files/{id}/trace
 */
final class ScaniiTraceResult extends ScaniiResult
{
    /**
     * @param list<ScaniiTraceEvent> $events
     */
    public function __construct(
        int $statusCode,
        string $rawResponse,
        public readonly string $resourceId,
        public readonly array $events,
        ?string $requestId = null,
        ?string $hostId = null,
        ?string $resourceLocation = null,
    ) {
        parent::__construct($statusCode, $rawResponse, $requestId, $hostId, $resourceLocation);
    }
}
