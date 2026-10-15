<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use JustSteveKing\Toolbox\Testing\Parsers\LinkHeader;
use PHPUnit\Framework\Assert;

final class LinkAssertions
{
    /**
     * Every Link field in the response parses (RFC 8288, section 3), and one of them has the
     * given relation type, and the given target if one is passed.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertLinkHeader(TestResponse $response, string $rel, ?string $uri = null): void
    {
        $links = self::links($response, $rel);

        Assert::assertNotEmpty($links, "The response has a Link with rel=\"{$rel}\".");

        if ($uri !== null) {
            Assert::assertContains($uri, array_column($links, 'uri'), "The rel=\"{$rel}\" Link points at [{$uri}].");
        }
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertNoLinkHeader(TestResponse $response, string $rel): void
    {
        Assert::assertEmpty(self::links($response, $rel), "The response has no Link with rel=\"{$rel}\".");
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<array{uri: string, params: array<string, string|null>}>
     */
    private static function links(TestResponse $response, string $rel): array
    {
        $values = array_values(array_filter($response->headers->all('link'), is_string(...)));

        try {
            return LinkHeader::withRel($values, $rel);
        } catch (InvalidArgumentException $e) {
            Assert::fail($e->getMessage());
        }
    }
}
