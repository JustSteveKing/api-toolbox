<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Parsers;

/**
 * A String, Token or Byte Sequence from a Structured Field, keeping which of the three it was:
 * "api" and api are different values to RFC 9651, and a field definition can require one.
 */
final readonly class StructuredValue
{
    public const string STRING = 'string';

    public const string TOKEN = 'token';

    public const string BYTES = 'bytes';

    public function __construct(
        public string $type,
        public string $value,
    ) {}
}
