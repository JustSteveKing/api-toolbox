<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class ConditionalRequestAssertions
{
    /**
     * RFC 9110 section 8.8.3: entity-tag = [ "W/" ] DQUOTE *etagc DQUOTE.
     */
    private const string ENTITY_TAG = '/^(W\/)?"[\x21\x23-\x7E\x80-\xFF]*"$/';

    /**
     * Exactly one ETag field, in valid entity-tag syntax, optionally equal to $etag (written with
     * its quotes, and W/ if weak) or of the given strength.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertETag(TestResponse $response, ?string $etag = null, ?bool $weak = null): void
    {
        $values = $response->headers->all('etag');

        Assert::assertCount(1, $values, 'The response has exactly one ETag field.');

        $value = (string) $values[0];

        Assert::assertMatchesRegularExpression(self::ENTITY_TAG, $value, "[{$value}] is a valid entity-tag (RFC 9110, section 8.8.3).");

        if ($etag !== null) {
            Assert::assertSame($etag, $value, 'The ETag matches.');
        }

        if ($weak !== null) {
            Assert::assertSame($weak, str_starts_with($value, 'W/'), $weak ? 'The ETag is weak.' : 'The ETag is strong.');
        }
    }

    /**
     * A 304 with no content (RFC 9110, section 15.4.5).
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertNotModified(TestResponse $response): void
    {
        $response->assertStatus(304);

        Assert::assertSame('', (string) $response->getContent(), 'A 304 has no content (RFC 9110, section 15.4.5).');
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertPreconditionFailed(TestResponse $response): void
    {
        $response->assertStatus(412);
    }

    /**
     * A 428 that explains itself, which RFC 6585 section 3 says it SHOULD, and that no cache will
     * store, which the same section says MUST NOT happen.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertPreconditionRequired(TestResponse $response): void
    {
        $response->assertStatus(428);

        Assert::assertNotSame('', (string) $response->getContent(), 'A 428 explains how to resubmit (RFC 6585, section 3).');
        Assert::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
            'A 428 is not stored by a cache (RFC 6585, section 3).',
        );
    }
}
