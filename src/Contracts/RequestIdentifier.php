<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Contracts;

/**
 * The current request's ID, for features that refer to it without depending on the Negotiation
 * feature, which assigns it. Bound only while that feature assigns request IDs.
 */
interface RequestIdentifier
{
    public function current(): ?string;
}
