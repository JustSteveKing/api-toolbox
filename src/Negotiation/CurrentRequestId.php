<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation;

use JustSteveKing\Toolbox\Contracts\RequestIdentifier;

/**
 * The ID AssignRequestId gave the current request. Bound as scoped, so it is reset between
 * requests under Octane.
 */
final class CurrentRequestId implements RequestIdentifier
{
    public ?string $id = null;

    public function current(): ?string
    {
        return $this->id;
    }
}
