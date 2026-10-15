<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails\Contracts;

/**
 * Turns the type given on a problem into the URI written to the "type" member.
 *
 * This is the seam for pointing types at an external catalogue: bind your own implementation to
 * resolve slugs against it.
 */
interface TypeResolver
{
    public function resolve(string $type): string;
}
