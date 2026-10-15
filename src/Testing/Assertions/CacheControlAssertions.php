<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class CacheControlAssertions
{
    private const string TOKEN = "[!#$%&'*+\\-.^_`|~0-9A-Za-z]+";

    /**
     * Cache-Control parses (RFC 9111, section 5.2) and has every directive given, with its value
     * where one is given:
     *
     *     ->assertCacheControl(['public', 'max-age' => 60, 'stale-while-revalidate' => 30, 'immutable'])
     *
     * Directive names compare case-insensitively (section 5.2). Other directives may be present.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @param  array<array-key, mixed>  $expected
     */
    public static function assertCacheControl(TestResponse $response, array $expected): void
    {
        $directives = self::parse(implode(', ', array_filter($response->headers->all('cache-control'), is_string(...))));

        foreach ($expected as $key => $value) {
            if (! is_string($value) && ! is_int($value)) {
                Assert::fail('Give each Cache-Control directive as a name, or as name => value.');
            }

            [$name, $wanted] = is_int($key) ? [strtolower((string) $value), null] : [strtolower($key), (string) $value];

            Assert::assertArrayHasKey($name, $directives, "Cache-Control has {$name}.");

            if ($wanted !== null) {
                Assert::assertSame($wanted, $directives[$name], "Cache-Control {$name} is {$wanted}.");
            }
        }
    }

    /**
     * Section 5.2: Cache-Control = #cache-directive, cache-directive = token [ "=" ( token /
     * quoted-string ) ].
     *
     * @return array<string, string|null>
     */
    private static function parse(string $field): array
    {
        $directives = [];

        preg_match_all('/(?:^|,)\s*('.self::TOKEN.')(?:\s*=\s*(?:('.self::TOKEN.')|"((?:[^"\\\\]|\\\\.)*)"))?\s*(?=,|$)/', $field, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $directives[strtolower($match[1])] = isset($match[3]) ? (string) preg_replace('/\\\\(.)/', '$1', $match[3]) : (($match[2] ?? '') === '' ? null : $match[2]);
        }

        return $directives;
    }
}
