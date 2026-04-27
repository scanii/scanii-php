<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * User entry nested inside ScaniiAccountInfo::$users.
 */
final class ScaniiAccountInfoUser
{
    public function __construct(
        public readonly ?string $creationDate,
        public readonly ?string $lastLoginDate,
    ) {
    }
}
