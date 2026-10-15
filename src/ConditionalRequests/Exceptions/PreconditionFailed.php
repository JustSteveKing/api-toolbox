<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests\Exceptions;

use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 412 Precondition Failed (RFC 9110, section 15.5.13).
 *
 * Carries the current ETag when there is one, so the client can re-fetch knowing what it is
 * looking for. Nothing requires it; apiguide.dev's stale-resource-version entry recommends it.
 */
final class PreconditionFailed extends HttpException
{
    public function __construct(?EntityTag $current = null)
    {
        parent::__construct(
            412,
            'The resource has changed since you last fetched it. Fetch it again and retry with its current ETag.',
            headers: $current === null ? [] : ['ETag' => $current->toString()],
        );
    }
}
