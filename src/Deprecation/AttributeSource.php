<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation;

use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use ReflectionClass;
use ReflectionMethod;

/**
 * Reads #[Deprecation] and #[Sunset] from a route's controller and action.
 *
 * An attribute on the action wins over the same attribute on the controller; the two kinds are
 * resolved separately, so a controller-wide Sunset can sit beside a per-action Deprecation.
 *
 * Only controller routes are read, as with Laravel's own #[Middleware] controller attributes.
 * Closure routes are not, because `route:cache` serialises closures and their attributes cannot
 * be relied on afterwards.
 */
final class AttributeSource implements DeprecationSource
{
    /**
     * @var array<string, Notice|null>
     */
    private array $resolved = [];

    public function forRoute(Route $route): ?Notice
    {
        $class = $route->getControllerClass();

        if ($class === null || ! class_exists($class)) {
            return null;
        }

        // An invokable controller's action name is the bare class, so getActionMethod() returns
        // the class name rather than "__invoke".
        $method = $route->getActionMethod();
        $method = $method === $class ? '__invoke' : $method;
        $key = $class.'@'.$method;

        if (! array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = $this->read(new ReflectionClass($class), $method);
        }

        return $this->resolved[$key];
    }

    /**
     * @param  ReflectionClass<object>  $class
     */
    private function read(ReflectionClass $class, string $method): ?Notice
    {
        $action = $class->hasMethod($method) ? $class->getMethod($method) : null;

        $deprecation = $this->attribute(Deprecation::class, $class, $action);
        $sunset = $this->attribute(Sunset::class, $class, $action);

        if ($deprecation === null && $sunset === null) {
            return null;
        }

        return new Notice(
            deprecatedAt: $deprecation?->at === null ? null : Notice::date($deprecation->at),
            deprecationLink: $deprecation?->link,
            sunsetAt: $sunset === null ? null : Notice::date($sunset->at),
            sunsetLink: $sunset?->link,
        );
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @param  ReflectionClass<object>  $class
     * @return T|null
     */
    private function attribute(string $attribute, ReflectionClass $class, ?ReflectionMethod $action): ?object
    {
        foreach ([$action, $class] as $target) {
            $found = $target?->getAttributes($attribute) ?? [];

            if ($found !== []) {
                return $found[0]->newInstance();
            }
        }

        return null;
    }
}
