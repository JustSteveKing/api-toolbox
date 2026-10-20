<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency\Exceptions;

use JustSteveKing\Toolbox\Support\Link;
use JustSteveKing\Toolbox\Support\Messages;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Draft section 2.7: a missing key where one is required SHOULD be a 400 with a link to the
 * documentation. With a documentation URL configured, that link is sent the way the draft's
 * own example sends it, as Link rel="describedby".
 */
final class InvalidIdempotencyKey extends BadRequestHttpException
{
    public static function missing(?string $documentation): self
    {
        return new self(
            Messages::get('idempotency.missing'),
            headers: self::describedBy($documentation),
        );
    }

    public static function malformed(?string $documentation): self
    {
        return new self(
            Messages::get('idempotency.malformed'),
            headers: self::describedBy($documentation),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function describedBy(?string $documentation): array
    {
        return $documentation === null ? [] : ['Link' => (new Link($documentation, 'describedby'))->toHeaderValue()];
    }
}
