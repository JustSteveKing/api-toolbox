<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class IdempotencyAssertions
{
    /**
     * Headers that identify a message rather than describe a result, so a replay sends its own.
     */
    public const array PER_MESSAGE = ['date', 'set-cookie', 'traceparent'];

    /**
     * The response is the stored result of $original (draft section 2.6): same status, same
     * content, and the same value for every header the original had, other than those that
     * identify the message rather than the result.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $original
     * @param  list<string>  $perMessage  Further headers to ignore, such as a request ID header.
     */
    public static function assertIdempotentReplay(TestResponse $response, TestResponse $original, array $perMessage = []): void
    {
        $ignored = [...self::PER_MESSAGE, ...array_map(strtolower(...), $perMessage)];

        Assert::assertSame($original->getStatusCode(), $response->getStatusCode(), 'A replay has the original status (draft section 2.6).');
        Assert::assertSame((string) $original->getContent(), (string) $response->getContent(), 'A replay has the original content (draft section 2.6).');

        foreach ($original->headers->all() as $name => $values) {
            if (in_array($name, $ignored, true)) {
                continue;
            }

            Assert::assertSame($values, $response->headers->all($name), "A replay has the original {$name} header.");
        }
    }
}
