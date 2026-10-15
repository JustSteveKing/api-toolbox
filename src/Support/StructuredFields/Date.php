<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * A Structured Field Date (RFC 9651, section 3.3.7): seconds since the Unix epoch.
 */
final readonly class Date
{
    public function __construct(
        public int $timestamp,
    ) {}
}
