<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class IdempotencyAssertions
{
    /**
     * The response is the stored result of $original (draft section 2.6): same status, same
     * content, and the same value for every header the original had, other than Date and
     * Set-Cookie, which describe a message rather than a result.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $original
     */
    public static function assertIdempotentReplay(TestResponse $response, TestResponse $original): void
    {
        Assert::assertSame($original->getStatusCode(), $response->getStatusCode(), 'A replay has the original status (draft section 2.6).');
        Assert::assertSame((string) $original->getContent(), (string) $response->getContent(), 'A replay has the original content (draft section 2.6).');

        foreach ($original->headers->all() as $name => $values) {
            if (in_array($name, ['date', 'set-cookie'], true)) {
                continue;
            }

            Assert::assertSame($values, $response->headers->all($name), "A replay has the original {$name} header.");
        }
    }
}
