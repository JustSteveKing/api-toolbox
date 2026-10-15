<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class ProblemDetailsAssertions
{
    private const string MEDIA_TYPE = 'application/problem+json';

    /**
     * The response is a well-formed RFC 9457 problem, optionally with a given status.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertProblem(TestResponse $response, ?int $status = null): void
    {
        if ($status !== null) {
            $response->assertStatus($status);
        }

        $contentType = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'))[0]));

        Assert::assertSame(self::MEDIA_TYPE, $contentType, 'A problem is sent as application/problem+json (RFC 9457, section 3).');

        $body = self::body($response);

        foreach (['type', 'title', 'detail', 'instance'] as $member) {
            if (array_key_exists($member, $body)) {
                Assert::assertIsString($body[$member], "\"{$member}\" is a string (RFC 9457, section 3.1).");
            }
        }

        if (array_key_exists('status', $body)) {
            Assert::assertIsInt($body['status'], '"status" is a number (RFC 9457, section 3.1.2).');
            Assert::assertSame($response->getStatusCode(), $body['status'], 'The "status" member matches the response status (RFC 9457, section 3.1.2).');
        }
    }

    /**
     * The response is a problem of the given type. A missing "type" is "about:blank" (section 3.1.1).
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertProblemType(TestResponse $response, string $type, ?int $status = null): void
    {
        self::assertProblem($response, $status);

        Assert::assertSame($type, self::body($response)['type'] ?? 'about:blank', 'The problem type matches.');
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return array<array-key, mixed>
     */
    private static function body(TestResponse $response): array
    {
        $body = json_decode((string) $response->getContent(), true);

        if (! is_array($body) || ($body !== [] && array_is_list($body))) {
            Assert::fail('A problem is a JSON object (RFC 9457, section 3).');
        }

        return $body;
    }
}
