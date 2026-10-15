<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests\Contracts;

use DateTimeInterface;
use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;

/**
 * Something a route is bound to that knows its own validators (RFC 9110, section 8.8).
 *
 * Preconditions on unsafe methods have to be evaluated before the method runs (section 13.1.1),
 * so they cannot come from the response. They come from the route-bound object implementing this.
 */
interface HasValidators
{
    public function entityTag(): ?EntityTag;

    public function lastModified(): ?DateTimeInterface;
}
