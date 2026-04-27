<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * Result of a synchronous file scan, returned by ScaniiClient::process and
 * ScaniiClient::retrieve.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
final class ScaniiProcessingResult extends ScaniiResult
{
    /**
     * @param list<string>          $findings
     * @param array<string, string> $metadata
     */
    public function __construct(
        int $statusCode,
        string $rawResponse,
        public readonly string $resourceId,
        public readonly ?string $contentType,
        public readonly ?int $contentLength,
        public readonly array $findings,
        public readonly ?string $checksum,
        public readonly ?string $creationDate,
        public readonly array $metadata,
        public readonly ?string $error = null,
        ?string $requestId = null,
        ?string $hostId = null,
        ?string $resourceLocation = null,
    ) {
        parent::__construct($statusCode, $rawResponse, $requestId, $hostId, $resourceLocation);
    }
}
