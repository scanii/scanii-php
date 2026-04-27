<?php

declare(strict_types=1);

namespace Scanii;

/**
 * Regional API endpoints. Pass one of the constants — or a custom URL via
 * the constructor — to ScaniiClient::create.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
final class ScaniiTarget
{
    public const string AUTO = 'https://api.scanii.com';
    public const string US1 = 'https://api-us1.scanii.com';
    public const string EU1 = 'https://api-eu1.scanii.com';
    public const string EU2 = 'https://api-eu2.scanii.com';
    public const string AP1 = 'https://api-ap1.scanii.com';
    public const string AP2 = 'https://api-ap2.scanii.com';
    public const string CA1 = 'https://api-ca1.scanii.com';
}
