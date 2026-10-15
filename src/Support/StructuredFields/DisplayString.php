<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * A Structured Field Display String (RFC 9651, section 3.3.8): Unicode text, distinct from a String.
 */
final readonly class DisplayString
{
    public function __construct(
        public string $value,
    ) {}
}
