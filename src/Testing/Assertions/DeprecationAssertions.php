<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use JustSteveKing\Toolbox\Testing\Parsers\HttpDate;
use JustSteveKing\Toolbox\Testing\Parsers\StructuredDate;
use PHPUnit\Framework\Assert;

final class DeprecationAssertions
{
    /**
     * A Deprecation field that is a valid Structured Field Date (RFC 9745, section 2.1),
     * optionally for a given moment and with a rel="deprecation" Link to $link (section 3).
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertDeprecated(TestResponse $response, DateTimeInterface|string|null $at = null, ?string $link = null): void
    {
        $deprecation = self::parse($response, 'Deprecation', StructuredDate::parse(...));

        if ($at !== null) {
            Assert::assertSame(self::timestamp($at), $deprecation->getTimestamp(), 'The deprecation date matches.');
        }

        if ($link !== null) {
            LinkAssertions::assertLinkHeader($response, 'deprecation', $link);
        }
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertNotDeprecated(TestResponse $response): void
    {
        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('Sunset');
    }

    /**
     * A Sunset field that is a valid HTTP-date (RFC 8594, section 3), optionally for a given
     * moment and with a rel="sunset" Link to $link (section 6). When the response is also
     * deprecated, the sunset is not earlier than the deprecation (RFC 9745, section 4).
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertSunset(TestResponse $response, DateTimeInterface|string|null $at = null, ?string $link = null): void
    {
        $sunset = self::parse($response, 'Sunset', HttpDate::parse(...));

        if ($at !== null) {
            Assert::assertSame(self::timestamp($at), $sunset->getTimestamp(), 'The sunset date matches.');
        }

        if ($response->headers->has('Deprecation')) {
            $deprecation = self::parse($response, 'Deprecation', StructuredDate::parse(...));

            Assert::assertGreaterThanOrEqual(
                $deprecation->getTimestamp(),
                $sunset->getTimestamp(),
                'Sunset is not earlier than Deprecation (RFC 9745, section 4).',
            );
        }

        if ($link !== null) {
            LinkAssertions::assertLinkHeader($response, 'sunset', $link);
        }
    }

    /**
     * Both fields are singletons: exactly one field line, parsed by $parser.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @param  callable(string): DateTimeImmutable  $parser
     */
    private static function parse(TestResponse $response, string $field, callable $parser): DateTimeImmutable
    {
        $values = $response->headers->all(strtolower($field));

        Assert::assertCount(1, $values, "The response has exactly one {$field} field.");

        try {
            return $parser((string) $values[0]);
        } catch (InvalidArgumentException $e) {
            Assert::fail($e->getMessage());
        }
    }

    /**
     * Strings are read as UTC when they carry no offset, as the attributes read them.
     */
    private static function timestamp(DateTimeInterface|string $at): int
    {
        return $at instanceof DateTimeInterface
            ? $at->getTimestamp()
            : (new DateTimeImmutable($at, new DateTimeZone('UTC')))->getTimestamp();
    }
}
