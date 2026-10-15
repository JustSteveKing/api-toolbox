<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Parsers;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Parses IMF-fixdate, the form RFC 9110 section 5.6.7 says senders MUST generate:
 *
 *     Sun, 06 Nov 1994 08:49:37 GMT
 *
 * The obsolete RFC 850 and asctime forms are rejected: recipients must accept them, but a server
 * must not send them, and these parsers check what a server sends.
 */
final class HttpDate
{
    private const string PATTERN = '/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun), (\d{2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d{4}) (\d{2}):(\d{2}):(\d{2}) GMT$/';

    public static function parse(string $value): DateTimeImmutable
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("[{$value}] is not an IMF-fixdate (RFC 9110, section 5.6.7).");
        }

        $date = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new DateTimeZone('UTC'));

        // createFromFormat rolls invalid dates over, and ignores a wrong day name, so the date is
        // formatted back and compared to catch "Mon, 31 Feb 2026".
        if ($date === false || $date->format('D, d M Y H:i:s').' GMT' !== $value) {
            throw new InvalidArgumentException("[{$value}] is not a real date, or its day name is wrong.");
        }

        return $date;
    }
}
