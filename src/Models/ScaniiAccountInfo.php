<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * Account metadata returned by ScaniiClient::retrieveAccountInfo.
 *
 * Mirrors the nested User and ApiKey shapes from the Java reference SDK.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
final class ScaniiAccountInfo extends ScaniiResult
{
    /**
     * @param array<string, ScaniiAccountInfoUser>   $users
     * @param array<string, ScaniiAccountInfoApiKey> $keys
     */
    public function __construct(
        int $statusCode,
        string $rawResponse,
        public readonly string $name,
        public readonly int $balance,
        public readonly int $startingBalance,
        public readonly ?string $billingEmail,
        public readonly ?string $subscription,
        public readonly ?string $creationDate,
        public readonly ?string $modificationDate,
        public readonly array $users,
        public readonly array $keys,
        ?string $requestId = null,
        ?string $hostId = null,
        ?string $resourceLocation = null,
    ) {
        parent::__construct($statusCode, $rawResponse, $requestId, $hostId, $resourceLocation);
    }
}
