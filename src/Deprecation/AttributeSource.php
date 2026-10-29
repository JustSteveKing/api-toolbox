<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
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
 * resolved separately, so a controller-wide Sunset can sit beside a per-action Deprecation. An
 * attribute with a query applies only when the request carries that query parameter, and wins
 * over one without.
 *
 * Only controller routes are read, as with Laravel's own #[Middleware] controller attributes.
 * Closure routes are not, because `route:cache` serialises closures and their attributes cannot
 * be relied on afterwards.
 */
final class AttributeSource implements DeprecationSource
{
    /**
     * The attributes on each action, read once, by kind; the notice itself depends on the request.
     *
     * @var array<string, array{deprecation: list<Deprecation>, sunset: list<Sunset>}>
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
            $reflection = new ReflectionClass($class);
            $action = $reflection->hasMethod($method) ? $reflection->getMethod($method) : null;

            $this->resolved[$key] = [
                'deprecation' => $this->attributes(Deprecation::class, $reflection, $action),
                'sunset' => $this->attributes(Sunset::class, $reflection, $action),
            ];
        }

        // Read when asked rather than held: this source is a singleton, and under Octane one
        // instance serves many requests.
        $request = Container::getInstance()->bound('request') ? Container::getInstance()->make('request') : null;
        $parameters = $request instanceof Request ? array_map(strval(...), $request->query->keys()) : [];

        $deprecation = $this->choose($this->resolved[$key]['deprecation'], $parameters);
        $sunset = $this->choose($this->resolved[$key]['sunset'], $parameters);

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
     * The attribute that applies to this request: one scoped to a query parameter the request
     * carries, or else one for the route itself. Attributes come action first, so within each of
     * those an action's attribute wins over its controller's.
     *
     * @template T of Deprecation|Sunset
     *
     * @param  list<T>  $attributes
     * @param  list<string>  $parameters  the query parameters the request carries
     * @return T|null
     */
    private function choose(array $attributes, array $parameters): ?object
    {
        foreach ($attributes as $attribute) {
            if ($attribute->query !== null && in_array($attribute->query, $parameters, true)) {
                return $attribute;
            }
        }

        foreach ($attributes as $attribute) {
            if ($attribute->query === null) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Every attribute of a kind, the action's before its controller's.
     *
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @param  ReflectionClass<object>  $class
     * @return list<T>
     */
    private function attributes(string $attribute, ReflectionClass $class, ?ReflectionMethod $action): array
    {
        $found = [];

        foreach ([$action, $class] as $target) {
            foreach ($target?->getAttributes($attribute) ?? [] as $reflected) {
                $found[] = $reflected->newInstance();
            }
        }

        return $found;
    }
}
