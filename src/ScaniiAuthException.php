<?php

declare(strict_types=1);

namespace Scanii;

/**
 * Thrown when the Scanii API rejects credentials (HTTP 401 or 403).
 */
class ScaniiAuthException extends ScaniiException
{
}
