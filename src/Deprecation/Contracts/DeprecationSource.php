<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation\Contracts;

use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\Deprecation\Notice;

/**
 * Where deprecation and sunset information comes from.
 *
 * The default reads #[Deprecation] and #[Sunset] attributes. Bind your own to read an external
 * manifest or registry instead; the middleware does not care where a Notice came from.
 */
interface DeprecationSource
{
    public function forRoute(Route $route): ?Notice;
}
