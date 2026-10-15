<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;

/**
 * Finds the route-bound object that supplies a request's validators: the first route parameter
 * implementing HasValidators. Parameters are bound by SubstituteBindings, so the conditional
 * middleware is registered to run after it.
 */
final class RouteValidators
{
    public static function for(Request $request): ?HasValidators
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return null;
        }

        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof HasValidators) {
                return $parameter;
            }
        }

        return null;
    }
}
