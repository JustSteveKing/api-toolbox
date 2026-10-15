<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class NegotiationAssertions
{
    /**
     * A valid version 00 traceparent (W3C Trace Context Level 1, section 3.2), optionally in the
     * given trace.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertTraceparent(TestResponse $response, ?string $traceId = null): void
    {
        $value = (string) $response->headers->get('traceparent');

        Assert::assertMatchesRegularExpression(
            '/^00-(?!0{32})[0-9a-f]{32}-(?!0{16})[0-9a-f]{16}-[0-9a-f]{2}$/',
            $value,
            "[{$value}] is a valid traceparent (Trace Context, section 3.2).",
        );

        if ($traceId !== null) {
            Assert::assertSame($traceId, substr($value, 3, 32), 'The response is in the expected trace.');
        }
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertRequestId(TestResponse $response, string $header, ?string $id = null): void
    {
        $value = $response->headers->get($header);

        Assert::assertNotNull($value, "The response has a {$header} header.");

        if ($id !== null) {
            Assert::assertSame($id, $value, 'The request ID matches.');
        }
    }
}
