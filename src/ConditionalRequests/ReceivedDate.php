<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Parses an HTTP-date received from a client, in any of the three forms RFC 9110 section 5.6.7
 * says a recipient MUST accept:
 *
 *     Sun, 06 Nov 1994 08:49:37 GMT    IMF-fixdate
 *     Sunday, 06-Nov-94 08:49:37 GMT   obsolete RFC 850 format
 *     Sun Nov  6 08:49:37 1994         ANSI C's asctime() format
 *
 * Returns null for anything else, including a list of dates, which sections 13.1.3 and 13.1.4
 * say to ignore.
 */
final class ReceivedDate
{
    private const array FORMATS = [
        '/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/' => 'D, d M Y H:i:s \G\M\T',
        '/^[A-Z][a-z]+, \d{2}-[A-Z][a-z]{2}-\d{2} \d{2}:\d{2}:\d{2} GMT$/' => 'l, d-M-y H:i:s \G\M\T',
        '/^[A-Z][a-z]{2} [A-Z][a-z]{2} [ \d]\d \d{2}:\d{2}:\d{2} \d{4}$/' => 'D M j H:i:s Y',
    ];

    public static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        foreach (self::FORMATS as $pattern => $format) {
            if (preg_match($pattern, $value) !== 1) {
                continue;
            }

            // asctime pads a single-digit day with a space: "Nov  6".
            $date = DateTimeImmutable::createFromFormat('!'.$format, (string) preg_replace('/ {2}/', ' ', $value), new DateTimeZone('UTC'));

            if ($date === false) {
                return null;
            }

            // Section 5.6.7: a two-digit year more than 50 years in the future is the most recent
            // past year with those digits. PHP's "y" maps 70-99 to the 1900s and 00-69 to the
            // 2000s, which this corrects for any year it gets wrong.
            if ($date > new DateTimeImmutable('+50 years')) {
                $date = $date->modify('-100 years');
            }

            return $date;
        }

        return null;
    }
}
