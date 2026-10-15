<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use JustSteveKing\Toolbox\Testing\Parsers\StructuredList;
use JustSteveKing\Toolbox\Testing\Parsers\StructuredValue;
use PHPUnit\Framework\Assert;

final class RateLimitAssertions
{
    /**
     * RateLimit-Policy and RateLimit are valid against draft-ietf-httpapi-ratelimit-headers-11,
     * and, when given, both describe $policy, with its remaining quota if $remaining is given.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertRateLimitHeaders(TestResponse $response, ?string $policy = null, ?int $remaining = null): void
    {
        $policies = self::parse($response, 'RateLimit-Policy');
        $limits = self::parse($response, 'RateLimit');

        foreach ($policies as $item) {
            // Section 3: the item MUST be a String, with q REQUIRED, a non-negative Integer.
            self::assertString($item['value'], 'A RateLimit-Policy item is a String (section 3).');
            self::assertNonNegativeInteger($item['params']['q'] ?? null, 'A RateLimit-Policy item has q, a non-negative Integer (section 3.1.1).');

            if (array_key_exists('w', $item['params'])) {
                Assert::assertTrue(is_int($item['params']['w']) && $item['params']['w'] > 0, 'w is a non-zero, non-negative Integer (section 3.1.3).');
            }

            if (array_key_exists('qu', $item['params'])) {
                self::assertString($item['params']['qu'], 'qu is a String (section 3.1.2).');
            }

            if (array_key_exists('pk', $item['params'])) {
                Assert::assertTrue(
                    $item['params']['pk'] instanceof StructuredValue && $item['params']['pk']->type === StructuredValue::BYTES,
                    'pk is a Byte Sequence (section 3.1.4).',
                );
            }
        }

        foreach ($limits as $item) {
            // Section 4 does not say the item MUST be a String, but it identifies the same policy
            // the String in section 3 names, and every example uses one.
            self::assertString($item['value'], 'A RateLimit item is a String naming its policy (section 4.1).');
            self::assertNonNegativeInteger($item['params']['r'] ?? null, 'A RateLimit item has r, a non-negative Integer (section 4.1.1).');

            if (array_key_exists('t', $item['params'])) {
                self::assertNonNegativeInteger($item['params']['t'], 't is a non-negative Integer (section 4.1.2).');
            }
        }

        if ($policy === null) {
            return;
        }

        Assert::assertContains($policy, self::names($policies), "RateLimit-Policy describes \"{$policy}\".");
        Assert::assertContains($policy, self::names($limits), "RateLimit reports \"{$policy}\".");

        if ($remaining !== null) {
            foreach ($limits as $item) {
                if ($item['value'] instanceof StructuredValue && $item['value']->value === $policy) {
                    Assert::assertSame($remaining, $item['params']['r'] ?? null, "\"{$policy}\" has {$remaining} remaining.");
                }
            }
        }
    }

    /**
     * Both fields can be split over several lines (sections 3 and 4).
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<array{value: StructuredValue|int|float|bool, params: array<string, StructuredValue|int|float|bool>}>
     */
    private static function parse(TestResponse $response, string $field): array
    {
        $values = array_values(array_filter($response->headers->all(strtolower($field)), is_string(...)));

        Assert::assertNotEmpty($values, "The response has a {$field} field.");

        try {
            $items = StructuredList::parse($values);
        } catch (InvalidArgumentException $e) {
            Assert::fail("{$field} is not a valid List: {$e->getMessage()}");
        }

        Assert::assertNotEmpty($items, "{$field} is a non-empty List.");

        return $items;
    }

    /**
     * @param  list<array{value: StructuredValue|int|float|bool, params: array<string, StructuredValue|int|float|bool>}>  $items
     * @return list<string>
     */
    private static function names(array $items): array
    {
        $names = [];

        foreach ($items as $item) {
            if ($item['value'] instanceof StructuredValue) {
                $names[] = $item['value']->value;
            }
        }

        return $names;
    }

    private static function assertString(mixed $value, string $message): void
    {
        Assert::assertTrue($value instanceof StructuredValue && $value->type === StructuredValue::STRING, $message);
    }

    private static function assertNonNegativeInteger(mixed $value, string $message): void
    {
        Assert::assertTrue(is_int($value) && $value >= 0, $message);
    }
}
