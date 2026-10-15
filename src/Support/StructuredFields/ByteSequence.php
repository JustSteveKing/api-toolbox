<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * A Structured Field Byte Sequence (RFC 9651, section 3.3.5), holding the decoded bytes.
 */
final readonly class ByteSequence
{
    public function __construct(
        public string $bytes,
    ) {}
}
