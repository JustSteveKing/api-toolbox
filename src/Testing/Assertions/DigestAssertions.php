<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class DigestAssertions
{
    private const array ALGORITHMS = ['sha-256' => 'sha256', 'sha-512' => 'sha512'];

    /**
     * Content-Digest (RFC 9530, section 2) is a Dictionary of Byte Sequences, and every sha-256
     * or sha-512 member matches the response content.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertContentDigest(TestResponse $response): void
    {
        self::assertDigest($response, 'Content-Digest');
    }

    /**
     * Repr-Digest (RFC 9530, section 3), likewise. For a response without ranges or content
     * codings, the representation data is the content.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertReprDigest(TestResponse $response): void
    {
        self::assertDigest($response, 'Repr-Digest');
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertAcceptsPatch(TestResponse $response, string $mediaType): void
    {
        $values = array_map(
            static fn (string $type): string => strtolower(trim(explode(';', $type)[0])),
            explode(',', (string) $response->headers->get('Accept-Patch')),
        );

        Assert::assertContains(strtolower($mediaType), $values, "Accept-Patch lists {$mediaType} (RFC 5789, section 3.1).");
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    private static function assertDigest(TestResponse $response, string $field): void
    {
        $value = $response->headers->get($field);

        Assert::assertIsString($value, "The response has a {$field} field.");

        $checked = 0;

        foreach (explode(',', $value) as $member) {
            Assert::assertMatchesRegularExpression(
                '/^\s*[a-z*][a-z0-9_\-.*]*=:[A-Za-z0-9+\/=]*:\s*$/',
                $member,
                "[{$member}] is a Dictionary member with a Byte Sequence value (RFC 9530, section 2).",
            );

            [$algorithm, $digest] = explode('=', trim($member), 2);

            if (! isset(self::ALGORITHMS[$algorithm])) {
                continue;
            }

            Assert::assertSame(
                base64_encode(hash(self::ALGORITHMS[$algorithm], (string) $response->getContent(), true)),
                trim($digest, ':'),
                "The {$algorithm} {$field} matches the content.",
            );

            $checked++;
        }

        Assert::assertGreaterThan(0, $checked, "{$field} has a sha-256 or sha-512 digest to check.");
    }
}
