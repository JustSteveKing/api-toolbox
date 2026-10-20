<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency\Exceptions;

use JustSteveKing\Toolbox\Contracts\HasProblemType;
use JustSteveKing\Toolbox\Support\ApiGuide;
use JustSteveKing\Toolbox\Support\Messages;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The two misuses of a key in draft section 2.7, both typed as apiguide.dev's
 * idempotency-key-conflict, which tells them apart by status:
 *
 *     422  the key was used before with a different payload; never retry it as is
 *     409  the original request is still in flight; retry unchanged once it finishes
 */
final class IdempotencyKeyConflict extends HttpException implements HasProblemType
{
    public static function differentPayload(): self
    {
        return new self(
            422,
            Messages::get('idempotency.conflict'),
        );
    }

    public static function inFlight(int $retryAfter): self
    {
        return new self(
            409,
            Messages::get('idempotency.in_flight'),
            headers: ['Retry-After' => (string) $retryAfter],
        );
    }

    public function problemType(): string
    {
        return ApiGuide::IDEMPOTENCY_KEY_CONFLICT;
    }

    public function problemTitle(): string
    {
        return 'Idempotency Key Conflict';
    }
}
