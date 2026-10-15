<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * A Structured Field Token (RFC 9651, section 3.3.4): an identifier, distinct from a String.
 */
final readonly class Token
{
    public function __construct(
        public string $value,
    ) {}
}
