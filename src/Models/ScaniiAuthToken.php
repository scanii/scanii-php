<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * Short-lived bearer token returned by ScaniiClient::createAuthToken.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
final class ScaniiAuthToken extends ScaniiResult
{
    public function __construct(
        int $statusCode,
        string $rawResponse,
        public readonly string $resourceId,
        public readonly ?string $creationDate,
        public readonly ?string $expirationDate,
        ?string $requestId = null,
        ?string $hostId = null,
        ?string $resourceLocation = null,
    ) {
        parent::__construct($statusCode, $rawResponse, $requestId, $hostId, $resourceLocation);
    }
}
