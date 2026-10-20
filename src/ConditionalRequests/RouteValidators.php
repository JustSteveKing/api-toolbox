<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;

/**
 * Finds the route-bound object that supplies a request's validators: the last route parameter
 * implementing HasValidators. On a nested route such as documents/{document}/comments/{comment}
 * the URI names its parents first, so the last bound parameter is the resource the request is
 * about. Parameters are bound by SubstituteBindings, so the conditional middleware is registered
 * to run after it.
 */
final class RouteValidators
{
    public static function for(Request $request): ?HasValidators
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return null;
        }

        $resource = null;

        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof HasValidators) {
                $resource = $parameter;
            }
        }

        return $resource;
    }
}
