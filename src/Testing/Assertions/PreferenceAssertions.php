<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class PreferenceAssertions
{
    /**
     * Preference-Applied lists the preference (RFC 7240, section 3), with its value when given.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertPreferenceApplied(TestResponse $response, string $preference, ?string $value = null): void
    {
        $applied = self::applied($response);

        Assert::assertArrayHasKey(strtolower($preference), $applied, "Preference-Applied lists {$preference}.");

        if ($value !== null) {
            Assert::assertSame($value, $applied[strtolower($preference)], "Preference-Applied gives {$preference}={$value}.");
        }
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertPreferenceNotApplied(TestResponse $response, string $preference): void
    {
        Assert::assertArrayNotHasKey(strtolower($preference), self::applied($response), "Preference-Applied does not list {$preference}.");
    }

    /**
     * A 202 pointing at a status monitor (RFC 9110, section 15.3.3) with Location, and saying when
     * to ask again with Retry-After. Returns the monitor's URL.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertOperationAccepted(TestResponse $response): string
    {
        $response->assertStatus(202);

        $location = $response->headers->get('Location');

        Assert::assertIsString($location, 'A 202 points at its status monitor with Location.');
        Assert::assertMatchesRegularExpression('/^\d+$/', (string) $response->headers->get('Retry-After'), 'A 202 says when to check with Retry-After in seconds.');

        return $location;
    }

    /**
     * Section 3: applied-pref = token [ BWS "=" BWS word ].
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return array<string, string|null>
     */
    private static function applied(TestResponse $response): array
    {
        $applied = [];

        foreach (array_filter($response->headers->all('preference-applied'), is_string(...)) as $field) {
            foreach (explode(',', $field) as $member) {
                if (preg_match('/^\s*([^=\s]+)\s*(?:=\s*"?([^"]*)"?)?\s*$/', $member, $match) === 1) {
                    $applied[strtolower($match[1])] = isset($match[2]) && $match[2] !== '' ? $match[2] : null;
                }
            }
        }

        return $applied;
    }
}
