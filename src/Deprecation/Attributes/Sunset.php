<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation\Attributes;

use Attribute;

/**
 * When a controller, or one of its actions, is expected to stop responding (RFC 8594).
 *
 *     #[Sunset(at: '2027-01-01T00:00:00Z', link: 'https://docs.example.com/sunset-policy')]
 *
 * A date without an offset is read as UTC.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Sunset
{
    public function __construct(
        public string $at,
        public ?string $link = null,
    ) {}
}
