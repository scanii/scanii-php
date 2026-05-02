<?php

declare(strict_types=1);

namespace Scanii\Models;

/**
 * A single processing event within a ScaniiTraceResult.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
final class ScaniiTraceEvent
{
    public function __construct(
        public readonly ?string $timestamp,
        public readonly string $message,
    ) {
    }
}
