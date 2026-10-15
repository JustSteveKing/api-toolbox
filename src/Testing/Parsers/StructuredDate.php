<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Parsers;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Parses an RFC 9651 Date item as a whole field value: "@" then an Integer (section 3.3.7), and
 * an Integer is an optional "-" and one to fifteen digits (section 3.3.1).
 */
final class StructuredDate
{
    public static function parse(string $value): DateTimeImmutable
    {
        if (preg_match('/^@(-?\d{1,15})$/', $value, $match) !== 1) {
            throw new InvalidArgumentException("[{$value}] is not a Structured Field Date (RFC 9651, section 3.3.7).");
        }

        return new DateTimeImmutable('@'.$match[1]);
    }
}
