<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * What a client should be told about a resource's deprecation and sunset.
 *
 * Deliberately free of routes and attributes: it is what any DeprecationSource produces, whether
 * it read attributes, a manifest, or something else.
 */
final readonly class Notice
{
    public function __construct(
        public ?DateTimeImmutable $deprecatedAt = null,
        public ?string $deprecationLink = null,
        public ?DateTimeImmutable $sunsetAt = null,
        public ?string $sunsetLink = null,
    ) {
        if ($deprecatedAt === null && $deprecationLink === null && $sunsetAt === null) {
            throw new InvalidArgumentException('A notice needs a deprecation date, a deprecation link or a sunset date.');
        }

        // RFC 9745, section 4: the Sunset timestamp MUST NOT be earlier than the Deprecation one.
        if ($deprecatedAt !== null && $sunsetAt !== null && $sunsetAt < $deprecatedAt) {
            throw new InvalidArgumentException(sprintf(
                'Sunset (%s) must not be earlier than deprecation (%s); see RFC 9745, section 4.',
                $sunsetAt->format(DATE_ATOM),
                $deprecatedAt->format(DATE_ATOM),
            ));
        }

        foreach ([$deprecationLink, $sunsetLink] as $link) {
            if ($link !== null && ($link === '' || preg_match('/[\s<>"]/', $link) === 1)) {
                throw new InvalidArgumentException("[{$link}] cannot be written inside a Link header's angle brackets (RFC 8288, section 3).");
            }
        }
    }

    /**
     * Parses a date as written in an attribute or a manifest. No offset means UTC.
     */
    public static function date(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            throw new InvalidArgumentException("[{$value}] is not a date.", previous: $e);
        }
    }
}
