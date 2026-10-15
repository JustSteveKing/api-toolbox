<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * An Item (RFC 9651, section 3.3): a bare item with ordered parameters.
 *
 * Bare items map to PHP as: Integer int, Decimal float, String string, Boolean bool, and Token,
 * Byte Sequence, Date and Display String their own classes, so a String and a Token that read
 * the same stay distinct.
 *
 * @phpstan-type BareItem int|float|string|bool|Token|ByteSequence|Date|DisplayString
 */
final readonly class Item
{
    /**
     * @param  BareItem  $value
     * @param  array<string, BareItem>  $parameters
     */
    public function __construct(
        public int|float|string|bool|Token|ByteSequence|Date|DisplayString $value,
        public array $parameters = [],
    ) {}
}
