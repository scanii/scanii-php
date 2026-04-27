<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * API key entry nested inside ScaniiAccountInfo::$keys.
 */
final class ScaniiAccountInfoApiKey
{
    /**
     * @param list<string> $detectionCategoriesEnabled
     * @param list<string> $tags
     */
    public function __construct(
        public readonly bool $active,
        public readonly ?string $creationDate,
        public readonly ?string $lastSeenDate,
        public readonly array $detectionCategoriesEnabled,
        public readonly array $tags,
    ) {
    }
}
